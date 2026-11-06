<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    config(['services.phone_login.enabled' => true, 'services.phone_login.pilot_host' => 'pilot.test', 'inertia.ssr.enabled' => false]);
    $this->origin = 'https://pilot.test';
    $this->phone = '+2348012345678';
    Http::fake(['*/pin?*' => Http::response(['smsStatus' => 'MESSAGE_SENT', 'pinId' => 'pin']), '*/verify' => Http::response(['verified' => true])]);
    config(['services.infobip.base_url' => 'https://trial.api.infobip.com']);
});

test('a farmer accepts a phone invitation then returns to the same farm without email', function () {
    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $team->members()->attach($owner, ['role' => 'owner']);
    $this->actingAs($owner)->post(route('teams.invitations.store', $team), ['phone' => $this->phone, 'role' => 'operator'])->assertSessionHasNoErrors();
    $invite = $team->invitations()->firstOrFail();
    $link = $this->origin.'/login/phone?invite='.$invite->code;
    $this->get(route('teams.edit', $team))->assertInertia(fn (AssertableInertia $page) => $page->where('invitations.0.phone_url', $link));
    $this->post(route('logout'));

    $this->withHeader('CF-IPCountry', 'QA')->get($link)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('auth/PhoneLogin')->where('callingCode', '+234')->where('pilot', true));
    $this->post($this->origin.'/login/phone/code', ['phone' => $this->phone, 'invite' => $invite->code])->assertSessionHasNoErrors();
    $this->post($this->origin.'/login/phone/verify', ['name' => 'Pilot Farmer', 'otp' => '123456'])
        ->assertRedirect($this->origin.'/'.$team->slug.'/dashboard')->assertCookie('phone_country', 'NG');
    $farmer = User::where('phone', $this->phone)->firstOrFail();
    expect($farmer->email)->toBeNull()->and($invite->refresh()->isAccepted())->toBeTrue();
    $this->get($this->origin.'/'.$team->slug.'/field-diary')->assertOk();
    $this->post($this->origin.'/logout');
    $this->travel(61)->seconds();
    $this->post($this->origin.'/login/phone/code', ['phone' => $this->phone])->assertSessionHasNoErrors();
    $this->post($this->origin.'/login/phone/verify', ['otp' => '123456'])->assertRedirect($this->origin.'/'.$team->slug.'/dashboard');
    $this->assertAuthenticatedAs($farmer);
    $other = Team::factory()->create();
    $other->members()->attach($farmer, ['role' => 'operator']);
    $this->get($this->origin.'/'.$other->slug.'/field-diary')->assertForbidden();
    $team->members()->detach($farmer);
    $this->get($this->origin.'/'.$team->slug.'/field-diary')->assertForbidden();
});

test('pilot exposes only the phone entry and resolves a locked country prefix', function () {
    $this->get($this->origin.'/')->assertRedirect($this->origin.'/login/phone');
    $this->get($this->origin.'/login?method=email')->assertRedirect($this->origin.'/login/phone');
    $this->get($this->origin.'/register')->assertNotFound();
    $this->post($this->origin.'/login', ['email' => 'a@example.test', 'password' => 'guess'])->assertNotFound();
    $this->get($this->origin.'/two-factor-challenge')->assertForbidden();
    $this->get($this->origin.'/login/phone')->assertInertia(fn (AssertableInertia $p) => $p->where('callingCode', '+234'));
    $this->get($this->origin.'/login/phone?country=QA')->assertInertia(fn (AssertableInertia $p) => $p->where('callingCode', '+974'));
    $this->withHeader('CF-IPCountry', 'QA')->get($this->origin.'/login/phone')->assertInertia(fn (AssertableInertia $p) => $p->where('callingCode', '+974'));
});

test('pilot refuses unsolicited SMS and rechecks membership after sending', function () {
    $user = User::factory()->create(['phone' => $this->phone, 'phone_verified_at' => now()]);
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'operator']);
    $invite = TeamInvitation::factory()->create(['team_id' => $team->id, 'phone' => $this->phone, 'email' => null]);
    $this->post($this->origin.'/login/phone/code', ['phone' => $this->phone])->assertSessionHasNoErrors();
    $this->post($this->origin.'/login/phone/code', ['phone' => '+2348012345679', 'invite' => $invite->code])->assertSessionHasNoErrors();
    Http::assertNothingSent();
    $invite->update(['accepted_at' => now()]);
    $this->travel(61)->seconds();
    $this->post($this->origin.'/login/phone/code', ['phone' => $this->phone]);
    Http::assertSentCount(1);
    $team->members()->detach($user);
    $this->post($this->origin.'/login/phone/verify', ['otp' => '123456'])->assertForbidden();
    $this->assertGuest();
});

test('pilot preserves an invited farmer’s authenticator challenge', function () {
    $user = User::factory()->withTwoFactor()->create(['phone' => $this->phone, 'phone_verified_at' => now()]);
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'operator']);
    $invite = TeamInvitation::factory()->create(['phone' => $this->phone, 'email' => null, 'role' => TeamRole::Operator]);
    $this->post($this->origin.'/login/phone/code', ['phone' => $this->phone, 'invite' => $invite->code]);
    $this->post($this->origin.'/login/phone/verify', ['name' => $user->name, 'otp' => '123456'])->assertRedirect($this->origin.'/two-factor-challenge');
    $this->get($this->origin.'/two-factor-challenge')->assertOk();
    $this->assertGuest();
    $this->post($this->origin.'/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])->assertRedirect($this->origin.'/'.$invite->team->slug.'/dashboard');
    $this->get($this->origin.'/'.$invite->team->slug.'/field-diary')->assertOk();
});

test('eligible pilot recipients have a daily SMS limit across sessions', function () {
    $invite = TeamInvitation::factory()->create(['phone' => $this->phone, 'email' => null]);
    for ($i = 0; $i < 6; $i++) {
        $this->post($this->origin.'/login/phone/code', ['phone' => $this->phone, 'invite' => $invite->code])->assertSessionHasNoErrors();
        $this->travel(61)->seconds();
    }
    Http::assertSentCount(5);
});
