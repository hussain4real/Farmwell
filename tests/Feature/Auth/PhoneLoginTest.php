<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\PhoneCallingCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->phone = '+97455550101';
    config([
        'services.phone_login.enabled' => true,
        'inertia.ssr.enabled' => false,
        'services.phone_login.allowed_number' => $this->phone,
        'services.infobip.base_url' => 'https://trial.api.infobip.com',
        'services.infobip.key' => 'test-key',
        'services.infobip.application_id' => 'app',
        'services.infobip.message_id' => 'message',
    ]);
    Http::preventStrayRequests();
    $this->invite = TeamInvitation::factory()->create(['phone' => $this->phone, 'email' => null, 'role' => TeamRole::Operator]);
});

test('an owner can invite a trial phone to join once and sign back into its farm without email', function () {
    $team = $this->invite->team;
    $this->invite->delete();
    $owner = User::factory()->create();
    $team->members()->attach($owner, ['role' => 'owner']);
    $this->actingAs($owner)->post(route('teams.invitations.store', $team), ['phone' => $this->phone, 'role' => 'operator'])->assertSessionHasNoErrors();
    $invite = $team->invitations()->firstOrFail();
    $this->get(route('teams.edit', $team))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('invitations.0.phone_url', route('phone-login.show', ['invite' => $invite->code])));
    $this->post(route('logout'));
    Http::fake(['*/pin?*' => Http::response(['smsStatus' => 'MESSAGE_SENT', 'pinId' => 'challenge']), '*/verify' => Http::response(['verified' => true])]);
    $this->get(route('phone-login.show', ['invite' => $invite->code]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('teamName', $team->name));
    $this->post(route('phone-login.send'), ['phone' => $this->phone, 'invite' => $invite->code])->assertSessionHasNoErrors();
    $this->post(route('phone-login.verify'), ['name' => 'Phone operator', 'otp' => '123456'])->assertRedirect(route('dashboard', $team));
    $user = User::where('phone', $this->phone)->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->email)->toBeNull()->and($user->email_verified_at)->toBeNull()->and($invite->fresh()->accepted_at)->not->toBeNull();
    $this->get(route('field-diary.index', $team))->assertOk();
    $this->get(route('finance.index', $team))->assertForbidden();
    $this->get(route('field-diary.index', Team::factory()->create()))->assertForbidden();
    $this->post(route('logout'));
    $this->travel(61)->seconds();
    $this->post(route('phone-login.send'), ['phone' => $this->phone])->assertSessionHasNoErrors();
    $this->post(route('phone-login.verify'), ['otp' => '123456'])->assertRedirect(route('dashboard', $team));
    $this->assertAuthenticatedAs($user);
    $this->post(route('logout'));
    $this->post(route('phone-login.verify'), ['otp' => '123456'])->assertSessionHasErrors('otp');
    $this->get(route('phone-login.show', ['invite' => $invite->code]))->assertGone();
});

test('phone login fails closed when the trial is disabled or the phone is unknown', function () {
    config(['services.phone_login.enabled' => false]);
    $this->get(route('phone-login.show'))->assertNotFound();
    $this->post(route('phone-login.send'))->assertNotFound();
    config(['services.phone_login.enabled' => true]);
    $this->post(route('phone-login.send'), ['phone' => $this->phone])->assertRedirect(route('phone-login.show'));
    $this->post(route('phone-login.send'), ['phone' => $this->phone])->assertSessionHasErrors('phone');
    $this->post(route('phone-login.verify'), ['otp' => '123456'])->assertSessionHasErrors('otp');
    $this->post(route('phone-login.send'), ['phone' => '+97455550999', 'invite' => $this->invite->code])->assertSessionHasNoErrors();
    Http::assertNothingSent();
});

test('a challenge cannot outlive its code, invitation or provider approval', function (string $reason) {
    Http::fake(['*/pin?*' => Http::response(['smsStatus' => 'MESSAGE_SENT', 'pinId' => 'pin']), '*/verify' => Http::response(['verified' => $reason !== 'wrong'])]);
    $this->post(route('phone-login.send'), ['phone' => $this->phone, 'invite' => $this->invite->code]);
    if ($reason === 'expired') {
        $this->travel(6)->minutes();
    } elseif ($reason === 'cancelled') {
        $this->invite->delete();
    }
    $response = $this->post(route('phone-login.verify'), ['name' => 'Tester', 'otp' => '123456']);
    if ($reason === 'cancelled') {
        $response->assertNotFound();
    } else {
        $response->assertSessionHasErrors('otp');
    }
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['phone' => $this->phone]);
})->with(['wrong', 'expired', 'cancelled']);

test('a verified phone cannot skip an existing authenticator challenge', function () {
    $user = User::factory()->create(['phone' => $this->phone, 'phone_verified_at' => now(), 'two_factor_secret' => encrypt('abc'), 'two_factor_confirmed_at' => now()]);
    $this->invite->team->members()->attach($user, ['role' => 'member']);
    Http::fake(['*/pin?*' => Http::response(['smsStatus' => 'MESSAGE_SENT', 'pinId' => 'pin']), '*/verify' => Http::response(['verified' => true])]);
    $this->post(route('phone-login.send'), ['phone' => $this->phone]);
    $this->post(route('phone-login.verify'), ['otp' => '123456'])->assertRedirect(route('two-factor.login'))->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('provider failures are safe and do not issue a usable challenge', function (string $failure) {
    if ($failure === 'invalid-host') {
        config(['services.infobip.base_url' => 'https://untrusted.example']);
    } elseif ($failure === 'connection') {
        Http::fake(fn () => throw new ConnectionException);
    } else {
        Http::fake(['*' => Http::response([], $failure === 'shape' ? 200 : 503)]);
    }
    $this->post(route('phone-login.send'), ['phone' => $this->phone, 'invite' => $this->invite->code])->assertSessionHasErrors('service')->assertSessionMissing('phone_challenge');
})->with(['invalid-host', 'connection', 'shape', 'status']);

test('the link alone cannot join through the legacy email invitation endpoint', function () {
    $user = User::factory()->create(['email' => null]);
    $this->actingAs($user)->get(route('invitations.accept', $this->invite))->assertSessionHasErrors('invitation');
    expect($this->invite->fresh()->isPending())->toBeTrue();
});

test('removing a phone-only operator revokes access even without a personal team', function () {
    $team = $this->invite->team;
    $owner = User::factory()->create();
    $team->members()->attach($owner, ['role' => 'owner']);
    $user = new User(['name' => 'Phone operator', 'password' => 'unused']);
    $user->phone = $this->phone;
    $user->phone_verified_at = now();
    $user->save();
    $team->members()->attach($user, ['role' => 'operator']);
    $user->switchTeam($team);
    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Updated operator'])->assertSessionHasNoErrors();
    $this->actingAs($owner)->delete(route('teams.members.destroy', [$team, $user]))->assertSessionHasNoErrors();
    expect($user->fresh()->current_team_id)->toBeNull();
    $this->post(route('logout'));
    $this->post(route('phone-login.send'), ['phone' => $this->phone])->assertSessionHasNoErrors();
    Http::assertNothingSent();
});

test('phone challenges are limited across sessions sharing an IP', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('phone-login.verify'), ['otp' => '123456'])->assertSessionHasErrors('otp');
    }
    $this->post(route('phone-login.verify'), ['otp' => '123456'])->assertTooManyRequests();
});

test('membership is granted only after the invited user completes their second factor', function () {
    $user = User::factory()->withTwoFactor()->create(['phone' => $this->phone, 'phone_verified_at' => now()]);
    Http::fake(['*/pin?*' => Http::response(['smsStatus' => 'MESSAGE_SENT', 'pinId' => 'pin']), '*/verify' => Http::response(['verified' => true])]);
    $this->post(route('phone-login.send'), ['phone' => $this->phone, 'invite' => $this->invite->code]);
    $this->post(route('phone-login.verify'), ['name' => 'Operator', 'otp' => '123456'])->assertRedirect(route('two-factor.login'));
    expect($this->invite->fresh()->isPending())->toBeTrue();
    $this->assertGuest();
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertRedirect(route('dashboard', $this->invite->team));
    expect($user->fresh()->belongsToTeam($this->invite->team))->toBeTrue();
});

test('ordinary members cannot see a pending phone number or its invite link in team settings', function () {
    $member = User::factory()->create();
    $team = $this->invite->team;
    $team->members()->attach($member, ['role' => 'member']);
    $this->actingAs($member)->get(route('teams.edit', $team))->assertInertia(fn (AssertableInertia $page) => $page->has('invitations', 0));
});

test('the locked calling code uses the recipient number regardless of visitor location', function () {
    $this->withHeader('CF-IPCountry', 'NG')->get(route('phone-login.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('callingCode', '+974'));
    $this->invite->update(['phone' => '+2348012345678']);
    $this->withHeader('CF-IPCountry', 'QA')->get(route('phone-login.show', ['invite' => $this->invite->code]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('callingCode', '+234'));
    expect(PhoneCallingCode::forRecipient('+15555550101'))->toBe('+1')
        ->and(PhoneCallingCode::forRecipient('+447700900123'))->toBe('+44')
        ->and(PhoneCallingCode::forRecipient('invalid'))->toBeNull();
});
