<?php

use App\Actions\Teams\SyncTeamRolePermissions;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\AuditEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('team member roles can be updated by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
            'reason' => 'Promoted for farm operations coverage',
        ]);

    $response->assertRedirect(route('teams.edit', $team));

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Admin->value);

    $auditEvent = AuditEvent::query()->firstOrFail();

    expect($auditEvent->team_id)->toBe($team->id)
        ->and($auditEvent->actor_id)->toBe($owner->id)
        ->and($auditEvent->action)->toBe('team_member.role_updated')
        ->and($auditEvent->old_values)->toBe(['role' => TeamRole::Member->value])
        ->and($auditEvent->new_values)->toBe([
            'role' => TeamRole::Admin->value,
            'user_id' => $member->id,
        ])
        ->and($auditEvent->metadata)->toBe(['member_id' => $member->id])
        ->and($auditEvent->reason)->toBe('Promoted for farm operations coverage');
});

test('team member roles cannot be updated by non owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertForbidden();
});

test('team members can be removed by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertRedirect(route('teams.edit', $team));

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('removed members lose team scoped package permissions', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    app(SyncTeamRolePermissions::class)->syncMembership($admin, $team, TeamRole::Admin);

    expect($admin->fresh()->hasTeamPermission($team, TeamPermission::ManageSettings))->toBeTrue();

    $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $admin]))
        ->assertRedirect(route('teams.edit', $team));

    setPermissionsTeamId($team->id);

    expect($admin->fresh()->belongsToTeam($team))->toBeFalse()
        ->and($admin->fresh()->hasTeamPermission($team, TeamPermission::ManageSettings))->toBeFalse()
        ->and($admin->fresh()->can(TeamPermission::ManageSettings->value))->toBeFalse()
        ->and(DB::table(config('permission.table_names.model_has_roles'))
            ->where('team_id', $team->id)
            ->where('model_id', $admin->id)
            ->where('model_type', $admin->getMorphClass())
            ->exists())->toBeFalse();

    setPermissionsTeamId(null);
});

test('team members cannot be removed by non owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertForbidden();
});

test('team owner cannot be removed', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $owner]));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('team member role cannot be set to owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Owner->value,
        ]);

    $response->assertSessionHasErrors('role');

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Member->value);
});

test('team owner role cannot be changed', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $owner]), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertForbidden();

    expect($owner->fresh()->teamRole($team))->toBe(TeamRole::Owner)
        ->and(AuditEvent::query()->where('team_id', $team->id)->exists())->toBeFalse();
});

test('removed member current team is set to personal team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $personalTeam = $member->personalTeam();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $team->id]);

    $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    expect($member->fresh()->current_team_id)->toEqual($personalTeam->id);
});
