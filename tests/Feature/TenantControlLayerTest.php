<?php

use App\Actions\Teams\SyncTeamRolePermissions;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('package permissions are scoped to the active team', function () {
    $user = User::factory()->create();
    $riceTeam = Team::factory()->create(['name' => 'Rice Team']);
    $maizeTeam = Team::factory()->create(['name' => 'Maize Team']);

    $riceTeam->members()->attach($user, ['role' => TeamRole::Member->value]);
    $maizeTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    $syncTeamRolePermissions = app(SyncTeamRolePermissions::class);
    $syncTeamRolePermissions->syncMembership($user, $riceTeam, TeamRole::Admin);
    $syncTeamRolePermissions->syncMembership($user, $maizeTeam, TeamRole::Member);

    expect(Role::query()->where('name', TeamRole::Admin->value)->whereNull('team_id')->exists())->toBeTrue()
        ->and($user->fresh()->hasTeamPermission($riceTeam, TeamPermission::ManageSettings))->toBeTrue()
        ->and($user->fresh()->hasTeamPermission($maizeTeam, TeamPermission::ManageSettings))->toBeFalse();

    setPermissionsTeamId($riceTeam->id);
    expect($user->fresh()->can(TeamPermission::ManageSettings->value))->toBeTrue();

    setPermissionsTeamId($maizeTeam->id);
    expect($user->fresh()->can(TeamPermission::ManageSettings->value))->toBeFalse();

    setPermissionsTeamId(null);
});

test('package role sync does not replace invitation-backed memberships', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'farm-admin@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = $team->invitations()->create([
        'email' => 'farm-admin@example.com',
        'role' => TeamRole::Admin,
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this
        ->actingAs($invitedUser)
        ->get(route('invitations.accept', $invitation))
        ->assertRedirect(route('dashboard'));

    expect($invitedUser->fresh()->teamRole($team))->toBe(TeamRole::Admin)
        ->and($invitedUser->fresh()->hasTeamPermission($team, TeamPermission::ManageSettings))->toBeTrue();
});
