<?php

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\PlatformRole;
use App\Enums\TeamRole;
use App\Models\AuditEvent;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
    $this->team = Team::factory()->create();
    $this->farm = Farm::factory()->for($this->team)->create(['name' => 'First farm']);
    $this->member = User::factory()->create();
    $this->team->members()->attach($this->member, ['role' => TeamRole::Operator->value]);
    $this->member->switchTeam($this->team);
    $this->actingAs($this->admin);
});

test('platform listings span workspaces without changing the active farm or exposing credentials', function () {
    $other = Farm::factory()->create(['name' => 'Second farm']);
    $this->get(route('platform.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('platform/Index')->where('canManagePlatform', true)
        ->has('farms.data', 2)->has('users.data', 2)->has('workspaces', 4)
        ->has('users.data.0', fn (Assert $user) => $user->missing('password')->missing('phone')->missing('two_factor_secret')->etc())
        ->where('currentTeam.id', $this->admin->current_team_id)->has('teams', 1));
    expect($this->admin->fresh()->current_team_id)->toBe($this->admin->current_team_id);
    $this->get(route('platform.index', ['search' => 'Second farm']))->assertInertia(fn (Assert $page) => $page
        ->has('farms.data', 1)->where('farms.data.0.id', $other->id)->has('users.data', 0));
    $this->get(route('platform.index', ['search' => $this->member->email]))->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 1)->where('users.data.0.id', $this->member->id));
    $this->get(route('platform.index', ['search' => ['bad']]))->assertSessionHasErrors('search');
});

test('every tenant role is denied every platform endpoint', function (TeamRole $role) {
    $this->team->members()->updateExistingPivot($this->member, ['role' => $role->value]);
    $this->actingAs($this->member);
    $this->get(route('platform.index'))->assertForbidden();
    $this->post(route('platform.farms.store'), [])->assertForbidden();
    $this->patch(route('platform.farms.update', $this->farm), [])->assertForbidden();
    $this->patch(route('platform.users.update', $this->admin), [])->assertForbidden();
    $this->put(route('platform.memberships.update', $this->admin), [])->assertForbidden();
    expect(AuditEvent::count())->toBe(0);
})->with(TeamRole::cases());

test('platform access still requires authentication and verified contact', function () {
    auth()->logout();
    $this->get(route('platform.index'))->assertRedirectToRoute('login');
    $this->admin->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($this->admin)->get(route('platform.index'))->assertRedirectToRoute('verification.notice');
});

test('platform privilege does not bypass tenant or private evidence authorization', function () {
    $this->get(route('dashboard', $this->team))->assertForbidden();
    $this->get(route('field-diary.index', $this->team))->assertForbidden();
    $this->get(route('finance.index', $this->team))->assertForbidden();
    $this->get(route('teams.edit', $this->team))->assertForbidden();
    Storage::fake('farmwell_private');
    $activity = FarmActivity::factory()->create([
        'team_id' => $this->team->id, 'farm_id' => $this->farm->id, 'recorded_by_id' => $this->member->id,
    ]);
    $media = $activity->addMedia(UploadedFile::fake()->image('private.jpg'))
        ->withCustomProperties(['visibility' => 'internal'])
        ->toMediaCollection(FarmActivity::EvidenceCollection, 'farmwell_private');
    $this->get(route('farm-evidence.show', ['current_team' => $this->team->slug, 'media' => $media->id]))->assertForbidden();
    expect($this->admin->can('manageFarmOperations', $this->team))->toBeFalse();
    $this->actingAs($this->member)->get(route('dashboard', $this->team))
        ->assertInertia(fn (Assert $page) => $page->where('canManagePlatform', false));
    $otherTeam = Team::factory()->create();
    $this->get(route('field-diary.index', $otherTeam))->assertForbidden();
});

test('farm creation and editing remain scoped and record actor reason and values', function () {
    $data = ['team_id' => $this->team->id, 'name' => 'New farm', 'farm_type' => 'aquaculture', 'location' => 'Abuja', 'reason' => 'Onboard the fish farm'];
    $this->from(route('platform.index'))->post(route('platform.farms.store'), $data)->assertRedirectToRoute('platform.index');
    $farm = Farm::where('name', 'New farm')->sole();
    expect($farm->team_id)->toBe($this->team->id);
    $data['name'] = 'Updated farm';
    unset($data['team_id']);
    $this->patch(route('platform.farms.update', $farm), $data)->assertRedirect();
    expect($farm->fresh()->name)->toBe('Updated farm')->and($this->farm->fresh()->name)->toBe('First farm');
    $event = AuditEvent::where('action', 'platform.farm_updated')->sole();
    expect($event->actor_id)->toBe($this->admin->id)->and($event->team_id)->toBe($this->team->id)
        ->and($event->old_values['name'])->toBe('New farm')->and($event->reason)->toBe('Onboard the fish farm');
    $this->get(route('platform.index'))->assertInertia(fn (Assert $page) => $page->has('auditEvents', 2));
    $this->patch(route('platform.farms.update', $farm), [...$data, 'team_id' => Team::factory()->create()->id])->assertSessionHasErrors('team_id');
    $this->patch(route('platform.farms.update', $farm), [...$data, 'name' => $this->farm->name])->assertSessionHasErrors('name');
    $this->post(route('platform.farms.store'), [...$data, 'team_id' => 999999])->assertSessionHasErrors('team_id');
    $this->post(route('platform.farms.store'), [...$data, 'team_id' => $this->team->id, 'reason' => ''])->assertSessionHasErrors('reason');
    $this->team->delete();
    $this->patch(route('platform.farms.update', $farm), $data)->assertNotFound();
});

test('user management edits names without changing credentials or platform privileges', function () {
    $this->patch(route('platform.users.update', $this->member), ['name' => 'Corrected name', 'reason' => 'Correct spelling'])->assertRedirect();
    expect($this->member->fresh()->name)->toBe('Corrected name');
    $event = AuditEvent::where('action', 'platform.user_updated')->sole();
    expect($event->team_id)->toBeNull()->and($event->subject_id)->toBe($this->member->id);
    $this->patch(route('platform.users.update', $this->member), ['name' => 'Bad', 'reason' => 'Escalate', 'platform_role' => 'super_admin', 'email' => 'other@example.test', 'phone' => '+2348000000000', 'password' => 'wrong'])->assertSessionHasErrors(['platform_role', 'email', 'phone', 'password']);
    $this->member->fill(['platform_role' => 'super_admin'])->save();
    expect($this->member->fresh()->platform_role)->toBeNull();
    $this->actingAs($this->member)->patch(route('profile.update'), ['name' => 'Member', 'email' => $this->member->email, 'platform_role' => 'super_admin'])->assertRedirect();
    expect($this->member->fresh()->platform_role)->toBeNull();
});

test('membership changes affect only the selected workspace and preserve tenant isolation', function () {
    $other = Team::factory()->create();
    $other->members()->attach($this->member, ['role' => TeamRole::Investor->value]);
    $data = ['team_id' => $this->team->id, 'role' => 'admin', 'reason' => 'Assign farm finance lead'];
    $this->put(route('platform.memberships.update', $this->member), $data)->assertRedirect();
    expect($this->member->fresh()->teamRole($this->team))->toBe(TeamRole::Admin)
        ->and($this->member->fresh()->teamRole($other))->toBe(TeamRole::Investor)
        ->and($this->member->fresh()->platform_role)->toBeNull();
    $this->actingAs($this->member)->get(route('finance.index', $this->team))->assertOk();
    $this->get(route('finance.index', $other))->assertForbidden();
    $this->actingAs($this->admin)->put(route('platform.memberships.update', $this->member), [...$data, 'role' => 'operator'])->assertRedirect();
    $this->actingAs($this->member)->get(route('finance.index', $this->team))->assertForbidden();
    $this->actingAs($this->admin)->put(route('platform.memberships.update', $this->member), [...$data, 'role' => 'remove'])->assertRedirect();
    expect($this->member->fresh()->belongsToTeam($this->team))->toBeFalse();
    $this->actingAs($this->member)->get(route('dashboard', $this->team))->assertForbidden();
    $this->actingAs($this->admin)->put(route('platform.memberships.update', $this->member), [...$data, 'role' => 'remove'])->assertNotFound();
    $this->put(route('platform.memberships.update', $this->member), $data)->assertRedirect();
    expect(AuditEvent::where('action', 'platform.membership_updated')->count())->toBe(4);
    $this->put(route('platform.memberships.update', $this->member), [...$data, 'role' => 'super_admin'])->assertSessionHasErrors('role');
    $this->put(route('platform.memberships.update', $this->member), [...$data, 'role' => 'owner'])->assertSessionHasErrors('role');
});

test('workspace owners cannot be removed or demoted through platform membership management', function () {
    $this->team->members()->updateExistingPivot($this->member, ['role' => 'owner']);
    foreach (['remove', 'operator'] as $role) {
        $this->put(route('platform.memberships.update', $this->member), ['team_id' => $this->team->id, 'role' => $role, 'reason' => 'Test'])->assertForbidden();
    }
    expect($this->member->fresh()->teamRole($this->team))->toBe(TeamRole::Owner);
});

test('platform assignment uses verified accounts and is audited without adding memberships', function () {
    $this->artisan('farmwell:platform-admin absent@example.test')->assertFailed();
    $unverified = User::factory()->unverified()->create();
    $this->artisan('farmwell:platform-admin '.$unverified->email)->assertFailed();
    $this->artisan('farmwell:platform-admin '.$this->member->email)->assertSuccessful();
    expect($this->member->fresh()->platform_role)->toBe(PlatformRole::SuperAdmin)
        ->and($this->member->teams()->count())->toBe(2);
    $this->artisan('farmwell:platform-admin '.$this->member->email.' --revoke')->assertSuccessful();
    expect($this->member->fresh()->platform_role)->toBeNull();
    expect(AuditEvent::where('action', 'platform.role_changed')->count())->toBe(2);
    $this->actingAs($this->member->fresh())->get(route('platform.index'))->assertForbidden();
});

test('platform role can be revoked when the account is no longer verified', function () {
    $this->admin->forceFill(['email_verified_at' => null])->save();
    $this->artisan('farmwell:platform-admin '.$this->admin->email.' --revoke')->assertSuccessful();
    expect($this->admin->fresh()->platform_role)->toBeNull();
});

test('administrative changes roll back when their audit record cannot be saved', function () {
    $this->mock(RecordAuditEvent::class)
        ->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->patch(route('platform.users.update', $this->member), [
        'name' => 'Unrecorded change', 'reason' => 'Atomic update test',
    ]))->toThrow(RuntimeException::class, 'Audit unavailable');
    expect($this->member->fresh()->name)->toBe($this->member->name);
});
