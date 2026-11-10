<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    config(['services.phone_login.enabled' => true, 'services.phone_login.pilot_host' => 'pilot.test', 'inertia.ssr.enabled' => false]);
    $this->origin = 'https://pilot.test';
    $this->phone = '+2348012345678';
    Http::preventStrayRequests()->fake([
        'https://trial.api.infobip.com/2fa/2/pin?*' => Http::response(['smsStatus' => 'MESSAGE_SENT', 'pinId' => 'pin']),
        'https://trial.api.infobip.com/2fa/2/pin/pin/verify' => Http::response(['verified' => true]),
    ]);
    config(['services.infobip.base_url' => 'https://trial.api.infobip.com']);
});

test('a farmer accepts a phone invitation then returns to the same farm without email', function (string $phone, string $provider) {
    config([
        'services.phone_login.nigerian_pilot_provider' => 'kudisms',
        'services.kudisms.key' => 'fake-key',
        'services.kudisms.application_code' => 'fake-app',
        'services.kudisms.template_code' => 'fake-template',
    ]);
    Http::fake([
        'https://my.kudisms.net/api/sendotp' => Http::sequence()
            ->push(['status' => 'success', 'error_code' => '000', 'verification_id' => 'join-id'])
            ->push(['status' => 'success', 'error_code' => '000', 'verification_id' => 'return-id']),
        'https://my.kudisms.net/api/verifyotp' => Http::sequence()
            ->push(['status' => 'error', 'error_code' => '003'])
            ->push(['status' => 'success', 'error_code' => '000', 'verification_id' => 'join-id'])
            ->push(['status' => 'success', 'error_code' => '000', 'verification_id' => 'return-id']),
    ]);

    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $team->members()->attach($owner, ['role' => 'owner']);
    $this->actingAs($owner)->post(route('teams.invitations.store', $team), ['phone' => $phone, 'role' => 'admin'])->assertSessionHasNoErrors();
    $invite = $team->invitations()->firstOrFail();
    $link = $this->origin.'/login/phone?invite='.$invite->code;
    $this->get(route('teams.edit', $team))->assertInertia(fn (AssertableInertia $page) => $page->where('invitations.0.phone_url', $link));
    $this->post(route('logout'));

    $this->withHeader('CF-IPCountry', 'QA')->get($link)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('auth/PhoneLogin')->where('callingCode', substr($phone, 0, 4))->where('pilot', true));
    $this->post($this->origin.'/login/phone/code', ['phone' => $phone, 'invite' => $invite->code])->assertSessionHasNoErrors();
    $this->get($link)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('awaitingCode', true)->where('codeLifetimeMinutes', $provider === 'kudisms' ? 10 : 5));
    if ($provider === 'kudisms') {
        $this->travel(6)->minutes();
        $this->post($this->origin.'/login/phone/verify', ['name' => 'Pilot Farmer', 'otp' => '000001'])->assertSessionHasErrors('otp');
        $this->assertGuest();
        expect($invite->refresh()->isPending())->toBeTrue();
    }
    $this->post($this->origin.'/login/phone/verify', ['name' => 'Pilot Farmer', 'otp' => '123456'])
        ->assertRedirect($this->origin.'/'.$team->slug.'/dashboard')->assertCookie('phone_country', $provider === 'kudisms' ? 'NG' : 'QA');
    $farmer = User::where('phone', $phone)->firstOrFail();
    expect($farmer->email)->toBeNull()->and($invite->refresh()->isAccepted())->toBeTrue();
    $this->get($this->origin.'/'.$team->slug.'/field-diary')->assertOk();
    $this->post($this->origin.'/logout');
    $this->travel(61)->seconds();
    $this->post($this->origin.'/login/phone/code', ['phone' => $phone])->assertSessionHasNoErrors();
    $this->post($this->origin.'/login/phone/verify', ['otp' => '123456'])->assertRedirect($this->origin.'/'.$team->slug.'/dashboard');
    $this->assertAuthenticatedAs($farmer);
    $this->followingRedirects()->get($this->origin.'/')
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'));
    $this->get($this->origin.'/'.$team->slug.'/finance')->assertOk();
    $other = Team::factory()->create();
    $other->members()->attach($farmer, ['role' => 'operator']);
    $this->get($this->origin.'/'.$other->slug.'/field-diary')->assertForbidden();
    $this->get($this->origin.'/'.$other->slug.'/finance')->assertForbidden();
    $team->members()->detach($farmer);
    $this->get($this->origin.'/'.$team->slug.'/field-diary')->assertForbidden();
    if ($provider === 'kudisms') {
        Http::assertSentCount(5);
        Http::assertSent(fn ($request) => $request->url() === 'https://my.kudisms.net/api/sendotp'
            && str_starts_with($request->header('Content-Type')[0], 'multipart/form-data;')
            && collect($request->data())->pluck('contents', 'name')->all() === [
                'token' => 'fake-key', 'senderID' => 'Farmwell', 'recipients' => substr($phone, 1),
                'appnamecode' => 'fake-app', 'templatecode' => 'fake-template', 'otp_type' => 'NUMERIC',
                'otp_length' => 6, 'otp_duration' => 10, 'otp_attempts' => 2, 'channel' => 'sms',
            ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://my.kudisms.net/api/verifyotp'
            && collect($request->data())->pluck('contents', 'name')->all() === [
                'token' => 'fake-key', 'verification_id' => 'return-id', 'otp' => '123456',
            ]);
    } else {
        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://trial.api.infobip.com/2fa/2/pin?'));
    }
})->with([
    'Nigeria with KudiSMS' => ['+2348012345678', 'kudisms'],
    'Qatar with Infobip' => ['+97450123456', 'infobip'],
]);

test('pilot and regular web sessions both expire after fourteen idle days', function () {
    config(['session.driver' => 'database', 'session.lifetime' => 120, 'session.lottery' => [1, 1]]);
    $this->freezeTime();

    $farmer = User::factory()->create(['phone' => $this->phone, 'phone_verified_at' => now()]);
    $team = Team::factory()->create();
    $team->members()->attach($farmer, ['role' => 'operator']);
    TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'phone' => $this->phone,
        'email' => null,
        'accepted_at' => now(),
    ]);
    $owner = User::factory()->create();
    $webOrigin = 'https://farmwell.test';
    $webCookie = config('session.cookie');
    $pilotCookie = $webCookie.'_pilot';
    $freshRequest = function () {
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
    };

    $challenge = $this->post($this->origin.'/login/phone/code', ['phone' => $this->phone])->assertSessionHasNoErrors();
    expect($challenge->getCookie($pilotCookie)->getExpiresTime())->toBe(now()->addHours(2)->timestamp)
        ->and($challenge->getCookie('XSRF-TOKEN')->getExpiresTime())->toBe(now()->addHours(2)->timestamp);
    $freshRequest();
    $pilotDashboard = $this->origin.'/'.$team->slug.'/dashboard';
    $signIn = $this->withCookie($pilotCookie, $challenge->getCookie($pilotCookie)->getValue())
        ->post($this->origin.'/login/phone/verify', ['otp' => '123456'])->assertRedirect($pilotDashboard);
    expect($signIn->getCookie($pilotCookie)->getExpiresTime())->toBe(now()->addDays(14)->timestamp)
        ->and($signIn->getCookie('XSRF-TOKEN')->getExpiresTime())->toBe(now()->addDays(14)->timestamp);
    $this->withCookie($pilotCookie, $signIn->getCookie($pilotCookie)->getValue());

    $freshRequest();
    $webSignIn = $this->post($webOrigin.'/login', ['email' => $owner->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertCookieMissing($pilotCookie);
    $this->assertAuthenticatedAs($owner);
    expect($webSignIn->getCookie($webCookie)->getExpiresTime())->toBe(now()->addDays(14)->timestamp)
        ->and($webSignIn->getCookie('XSRF-TOKEN')->getExpiresTime())->toBe(now()->addDays(14)->timestamp);

    $this->travel(13)->days();
    $freshRequest();
    $webDashboard = $webOrigin.'/'.$owner->personalTeam()->slug.'/dashboard';
    $webResumed = $this->withCookie($webCookie, $webSignIn->getCookie($webCookie)->getValue())
        ->get($webDashboard)->assertOk()->assertCookieMissing($pilotCookie);
    $this->assertAuthenticatedAs($owner);
    expect($webResumed->getCookie($webCookie)->getExpiresTime())->toBe(now()->addDays(14)->timestamp);
    $freshRequest();
    $resumed = $this->get($pilotDashboard)->assertOk()->assertCookieMissing($webCookie);
    $this->assertAuthenticatedAs($farmer);
    expect($resumed->getCookie($pilotCookie)->getExpiresTime())->toBe(now()->addDays(14)->timestamp);

    $this->travelTo(now()->addDays(14)->addSecond());
    $freshRequest();
    $this->withCookie($webCookie, $webResumed->getCookie($webCookie)->getValue())
        ->get($webDashboard)->assertRedirect($webOrigin.'/login');
    $this->assertGuest();
    $freshRequest();
    $this->withCookie($pilotCookie, $resumed->getCookie($pilotCookie)->getValue())
        ->followingRedirects()->get($pilotDashboard)->assertInertia(fn (AssertableInertia $page) => $page->component('auth/PhoneLogin'));
    $this->assertGuest();
    Http::assertSentCount(2);
});

test('pilot exposes only the phone entry and resolves a locked country prefix', function () {
    $this->get($this->origin.'/')->assertRedirect($this->origin.'/login/phone');
    $this->get($this->origin.'/login?method=email')->assertRedirect($this->origin.'/login/phone');
    $this->get($this->origin.'/register')->assertNotFound();
    $this->post($this->origin.'/login', ['email' => 'a@example.test', 'password' => 'guess'])->assertNotFound();
    $this->get($this->origin.'/two-factor-challenge')->assertForbidden();
    $this->get($this->origin.'/login/phone')
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest" crossorigin="use-credentials">', false)
        ->assertInertia(fn (AssertableInertia $p) => $p->where('callingCode', '+234'));
    $this->get($this->origin.'/login/phone?country=QA')->assertInertia(fn (AssertableInertia $p) => $p->where('callingCode', '+974'));
    $this->withHeader('CF-IPCountry', 'QA')->get($this->origin.'/login/phone')->assertInertia(fn (AssertableInertia $p) => $p->where('callingCode', '+974'));
});

test('KudiSMS failures do not leave a sign-in challenge', function (string $failure) {
    config([
        'services.phone_login.nigerian_pilot_provider' => 'kudisms',
        'services.kudisms.key' => $failure === 'missing-key' ? null : 'fake-key',
        'services.kudisms.application_code' => $failure === 'missing-app' ? null : 'fake-app',
        'services.kudisms.template_code' => 'fake-template',
    ]);
    Http::fake(['https://my.kudisms.net/api/sendotp' => fn () => match ($failure) {
        'connection' => throw new ConnectionException,
        'http-error' => Http::response([], 503),
        'invalid-json' => Http::response('provider unavailable'),
        'missing-id' => Http::response(['status' => 'success', 'error_code' => '000']),
    }]);
    $invite = TeamInvitation::factory()->create(['phone' => $this->phone, 'email' => null]);

    $this->post($this->origin.'/login/phone/code', ['phone' => $this->phone, 'invite' => $invite->code])
        ->assertSessionHasErrors('service')->assertSessionMissing('phone_challenge');
    $this->assertGuest();
    expect($invite->refresh()->isPending())->toBeTrue();
})->with(['missing-key', 'missing-app', 'connection', 'http-error', 'invalid-json', 'missing-id']);

test('pilot rejects malformed national numbers before creating an SMS challenge', function (string $number) {
    $this->post($this->origin.'/login/phone/code', ['phone' => $number])
        ->assertSessionHasErrors('phone')->assertSessionMissing('phone_challenge');
    Http::assertNothingSent();
})->with(['+9741234567', '+974123456789', '+9741234a678', '+234123456789', '+23412345678901']);

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
