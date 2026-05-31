<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use App\Policies\TeamPolicy;
use App\Providers\AppServiceProvider;
use App\Rules\TeamName;
use App\Rules\ValidTeamInvitation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\HttpException;

function farmwellRequestFor(User $user, bool $wantsJson = false): Request
{
    $request = Request::create('/quality-gate', 'GET', server: $wantsJson ? [
        'HTTP_ACCEPT' => 'application/json',
    ] : []);

    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $user);

    return $request;
}

test('team helper branches are covered for ownership switching and fallback paths', function () {
    $user = User::factory()->create();
    $ownedTeam = Team::factory()->create(['name' => 'Owned Farm Team']);
    $unrelatedTeam = Team::factory()->create(['name' => 'Other Farm Team']);

    $ownedTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    Team::query()->create(['name' => 'Acme Test', 'slug' => 'acme-test', 'is_personal' => false]);
    $teamWithIgnoredSuffix = Team::query()->create(['name' => 'Acme', 'is_personal' => false]);

    expect($user->ownedTeams()->whereKey($ownedTeam->id)->exists())->toBeTrue()
        ->and($user->switchTeam($unrelatedTeam))->toBeFalse()
        ->and($user->ownsTeam($ownedTeam))->toBeTrue()
        ->and($teamWithIgnoredSuffix->slug)->toBe('acme-1');
});

test('team roles expose permissions hierarchy and assignable roles', function () {
    expect(TeamRole::Owner->permissions())->toContain(TeamPermission::DeleteTeam)
        ->and(TeamRole::Admin->permissions())->toContain(TeamPermission::UpdateTeam)
        ->and(TeamRole::Member->permissions())->toBe([])
        ->and(TeamRole::Owner->level())->toBe(3)
        ->and(TeamRole::Admin->level())->toBe(2)
        ->and(TeamRole::Member->level())->toBe(1)
        ->and(TeamRole::Admin->isAtLeast(TeamRole::Member))->toBeTrue()
        ->and(TeamRole::Member->isAtLeast(TeamRole::Admin))->toBeFalse()
        ->and(TeamRole::assignable())->toBe([
            ['value' => 'admin', 'label' => 'Admin'],
            ['value' => 'member', 'label' => 'Member'],
        ]);
});

test('team membership middleware switches teams and enforces minimum roles', function () {
    Route::middleware(['web', 'auth', EnsureTeamMembership::class.':owner'])
        ->get('/quality-gate/{current_team}/owner', fn () => 'ok')
        ->name('quality-gate.owner');

    Route::middleware(['web', 'auth', EnsureTeamMembership::class.':invalid'])
        ->get('/quality-gate/{current_team}/invalid-role', fn () => 'ok')
        ->name('quality-gate.invalid-role');

    Route::getRoutes()->refreshNameLookups();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)
        ->get(route('quality-gate.owner', $team))
        ->assertOk();

    expect($owner->fresh()->isCurrentTeam($team))->toBeTrue();

    $this->actingAs($member)
        ->get(route('quality-gate.owner', $team))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('quality-gate.invalid-role', $team))
        ->assertForbidden();
});

test('Fortify response classes cover JSON responses and missing team guard', function () {
    $user = User::factory()->create();

    expect((new LoginResponse)->toResponse(farmwellRequestFor($user, wantsJson: true))->getStatusCode())->toBe(200)
        ->and((new RegisterResponse)->toResponse(farmwellRequestFor($user, wantsJson: true))->getStatusCode())->toBe(201)
        ->and((new TwoFactorLoginResponse)->toResponse(farmwellRequestFor($user, wantsJson: true))->getStatusCode())->toBe(200)
        ->and((new VerifyEmailResponse)->toResponse(farmwellRequestFor($user, wantsJson: true))->getStatusCode())->toBe(204);

    $teamlessUser = User::query()->create([
        'name' => 'No Team',
        'email' => 'no-team@example.com',
        'password' => Hash::make('password'),
    ]);

    expect(fn () => (new LoginResponse)->toResponse(farmwellRequestFor($teamlessUser)))
        ->toThrow(HttpException::class);
});

test('team invitations expose state helpers and notification payloads', function () {
    $owner = User::factory()->create(['name' => 'Farm Owner']);
    $team = Team::factory()->create(['name' => 'Rice Team']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'investor@example.com',
        'role' => TeamRole::Admin,
        'invited_by' => $owner->id,
    ]);

    $notification = new TeamInvitationNotification($invitation);
    $mail = $notification->toMail($owner);

    expect($invitation->isPending())->toBeTrue()
        ->and($invitation->getRouteKeyName())->toBe('code')
        ->and($invitation->team->memberships()->first()->team->is($team))->toBeTrue()
        ->and($mail->subject)->toBe("You've been invited to join Rice Team")
        ->and($notification->toArray($owner))->toMatchArray([
            'invitation_id' => $invitation->id,
            'team_id' => $team->id,
            'team_name' => 'Rice Team',
            'role' => TeamRole::Admin->value,
        ]);
});

test('team policies cover baseline permissions', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['is_personal' => false]);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $policy = new TeamPolicy;

    expect($policy->viewAny($owner))->toBeTrue()
        ->and($policy->create($owner))->toBeTrue()
        ->and($policy->view($owner, $team))->toBeTrue()
        ->and($policy->addMember($owner, $team))->toBeTrue()
        ->and($policy->updateMember($owner, $team))->toBeTrue()
        ->and($policy->removeMember($owner, $team))->toBeTrue()
        ->and($policy->inviteMember($owner, $team))->toBeTrue()
        ->and($policy->cancelInvitation($owner, $team))->toBeTrue()
        ->and($policy->delete($owner, $team))->toBeTrue();
});

test('quality rules cover reserved team names and invalid invitations', function () {
    $user = User::factory()->create(['email' => 'investor@example.com']);

    expect(Validator::make(['name' => 'login'], ['name' => [new TeamName]])->fails())->toBeTrue()
        ->and(Validator::make(['invitation' => 'bad'], ['invitation' => [new ValidTeamInvitation($user)]])->fails())->toBeTrue();

    $acceptedInvitation = TeamInvitation::factory()->accepted()->create([
        'email' => 'investor@example.com',
    ]);

    expect(Validator::make(['invitation' => $acceptedInvitation], ['invitation' => [new ValidTeamInvitation($user)]])->fails())->toBeTrue();
});

test('production defaults and Fortify rate limiters are configured', function () {
    $this->app->detectEnvironment(fn () => 'production');

    (new AppServiceProvider($this->app))->boot();

    expect(Password::default())->not->toBeNull();

    $twoFactorRequest = Request::create('/two-factor-challenge');
    $twoFactorRequest->setLaravelSession(app('session.store'));
    $twoFactorRequest->session()->put('login.id', 123);

    $passkeyRequest = Request::create('/passkeys/login/options', parameters: [
        'credential' => ['id' => 'credential-id'],
    ]);
    $passkeyRequest->setLaravelSession(app('session.store'));

    $fallbackPasskeyRequest = Request::create('/passkeys/login/options');
    $fallbackPasskeyRequest->setLaravelSession(app('session.store'));

    expect(RateLimiter::limiter('two-factor')($twoFactorRequest))->toBeInstanceOf(Limit::class)
        ->and(RateLimiter::limiter('passkeys')($passkeyRequest))->toBeInstanceOf(Limit::class)
        ->and(RateLimiter::limiter('passkeys')($fallbackPasskeyRequest))->toBeInstanceOf(Limit::class);

    $this->app->detectEnvironment(fn () => 'testing');

    (new AppServiceProvider($this->app))->boot();
});
