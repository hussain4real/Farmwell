<?php

use App\Enums\PlatformRole;
use App\Enums\TeamRole;
use App\Models\AuditEvent;
use App\Models\Team;
use App\Models\User;
use App\Support\PlatformAccess;
use App\Support\PlatformImpersonation;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
    $this->team = Team::factory()->create();
    $this->member = User::factory()->create();
    $this->team->members()->attach($this->member, ['role' => TeamRole::Operator->value]);
    $this->member->switchTeam($this->team);
});

test('super admin can impersonate a user, sees the impersonation banner context, and can exit back to platform', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.impersonate.store'), [
            'user_id' => $this->member->id,
            'reason' => 'Verify onboarding flow for operator',
        ])
        ->assertRedirect(route('dashboard', $this->team));

    expect(auth()->id())->toBe($this->member->id);

    $this->get(route('dashboard', $this->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManagePlatform', false)
            ->where('auth.user.id', $this->member->id)
            ->where('platformImpersonation.impersonator_id', $this->admin->id)
            ->where('platformImpersonation.impersonator_name', $this->admin->name)
            ->where('platformImpersonation.target_user_id', $this->member->id)
            ->where('platformImpersonation.target_user_name', $this->member->name)
            ->where('platformImpersonation.reason', 'Verify onboarding flow for operator'));

    $this->get(route('platform.index'))->assertForbidden();

    $started = AuditEvent::query()->where('action', 'platform.impersonation_started')->sole();
    expect($started->actor_id)->toBe($this->admin->id)
        ->and($started->subject_id)->toBe($this->member->id)
        ->and($started->team_id)->toBe($this->team->id)
        ->and($started->reason)->toBe('Verify onboarding flow for operator');

    $this->delete(route('platform.impersonate.destroy'))
        ->assertRedirect(route('platform.index'));

    expect(auth()->id())->toBe($this->admin->id);

    $this->get(route('platform.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManagePlatform', true)
            ->where('platformImpersonation', null));

    $ended = AuditEvent::query()->where('action', 'platform.impersonation_ended')->sole();
    expect($ended->actor_id)->toBe($this->admin->id)
        ->and($ended->subject_id)->toBe($this->member->id)
        ->and($ended->metadata['end_reason'])->toBe('manual');
});

test('impersonating an investor-only user redirects to the investor portal and a teamless user redirects to teams index', function () {
    $investor = User::factory()->create();
    $this->team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $investor->switchTeam($this->team);

    $this->actingAs($this->admin)
        ->post(route('platform.impersonate.store'), [
            'user_id' => $investor->id,
            'reason' => 'Verify investor portal statement view',
        ])
        ->assertRedirect(route('investor-portal.index', $this->team));

    $this->delete(route('platform.impersonate.destroy'))->assertRedirect(route('platform.index'));

    $teamless = User::factory()->create();
    $personalTeam = $teamless->personalTeam();
    $teamless->teams()->detach();
    $teamless->forceFill(['current_team_id' => null])->save();
    $personalTeam?->forceDelete();
    $teamless->refresh();

    $this->post(route('platform.impersonate.store'), [
        'user_id' => $teamless->id,
        'reason' => 'Verify team setup onboarding',
    ])
        ->assertRedirect(route('teams.index'));
});

test('starting impersonation clears any previous read-only workspace access session', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.access.store'), [
            'team_id' => $this->team->id,
            'reason' => 'Initial inspection',
        ])
        ->assertRedirect(route('dashboard', $this->team));

    expect(session()->has(PlatformAccess::SessionKey))->toBeTrue();

    $this->post(route('platform.impersonate.store'), [
        'user_id' => $this->member->id,
        'reason' => 'Test full farmer action flow',
    ])->assertRedirect(route('dashboard', $this->team));

    expect(session()->has(PlatformAccess::SessionKey))->toBeFalse();
});

test('non-super-admin users cannot start or exit impersonation without an active session', function (TeamRole $role) {
    $this->team->members()->updateExistingPivot($this->member, ['role' => $role->value]);

    $this->actingAs($this->member)
        ->post(route('platform.impersonate.store'), [
            'user_id' => $this->admin->id,
            'reason' => 'Unauthorized escalation',
        ])
        ->assertForbidden();

    $this->delete(route('platform.impersonate.destroy'))->assertForbidden();

    expect(AuditEvent::count())->toBe(0);
})->with(TeamRole::cases());

test('super admins cannot impersonate themselves, another super admin, or nested targets', function () {
    $otherAdmin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);

    $this->actingAs($this->admin)
        ->post(route('platform.impersonate.store'), [
            'user_id' => $this->admin->id,
            'reason' => 'Self check',
        ])
        ->assertForbidden();

    $this->post(route('platform.impersonate.store'), [
        'user_id' => $otherAdmin->id,
        'reason' => 'Peer check',
    ])->assertForbidden();

    $this->post(route('platform.impersonate.store'), [
        'user_id' => 999999,
        'reason' => 'Missing user',
    ])->assertSessionHasErrors('user_id');

    $this->post(route('platform.impersonate.store'), [
        'user_id' => $this->member->id,
        'reason' => 'Active impersonation',
    ])->assertRedirect(route('dashboard', $this->team));

    $thirdUser = User::factory()->create();
    $this->post(route('platform.impersonate.store'), [
        'user_id' => $thirdUser->id,
        'reason' => 'Nested impersonation',
    ])->assertForbidden();
});

test('expired impersonation restores the super admin and revoked impersonator logs out the session', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.impersonate.store'), [
            'user_id' => $this->member->id,
            'reason' => 'Support window',
        ])
        ->assertRedirect(route('dashboard', $this->team));

    $this->travel(PlatformImpersonation::DurationMinutes + 1)->minutes();

    $this->get(route('dashboard', $this->team))
        ->assertRedirect(route('platform.index'));

    expect(auth()->id())->toBe($this->admin->id)
        ->and(session()->has(PlatformImpersonation::SessionKey))->toBeFalse();

    $expiredAudit = AuditEvent::query()->where('action', 'platform.impersonation_ended')->sole();
    expect($expiredAudit->metadata['end_reason'])->toBe('expired');

    $this->travelBack();

    $this->post(route('platform.impersonate.store'), [
        'user_id' => $this->member->id,
        'reason' => 'Second session before revocation',
    ])->assertRedirect(route('dashboard', $this->team));

    $this->admin->forceFill(['platform_role' => null])->save();

    $this->get(route('dashboard', $this->team))
        ->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse()
        ->and(session()->has(PlatformImpersonation::SessionKey))->toBeFalse();
});

test('mismatched impersonation session context is automatically discarded', function () {
    $this->actingAs($this->member)
        ->withSession([
            PlatformImpersonation::SessionKey => [
                'id' => 'stale-id',
                'impersonator_id' => $this->admin->id,
                'impersonator_name' => $this->admin->name,
                'target_user_id' => $this->admin->id,
                'target_user_name' => $this->admin->name,
                'target_user_email' => $this->admin->email,
                'reason' => 'Stale context',
                'started_at' => now()->timestamp,
                'expires_at' => now()->addHour()->timestamp,
            ],
        ])
        ->get(route('dashboard', $this->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('platformImpersonation', null));

    expect(session()->has(PlatformImpersonation::SessionKey))->toBeFalse();
});
