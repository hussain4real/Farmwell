<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\InfobipVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

class PhoneLoginController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless(InfobipVerification::enabled(), 404);
        $invite = $this->invitation($request->query('invite'));

        return Inertia::render('auth/PhoneLogin', [
            'invite' => $invite?->code,
            'teamName' => $invite?->team->name,
            'awaitingCode' => $request->session()->has('phone_challenge')
                && $request->session()->get('phone_challenge.invite') === $invite?->code,
        ]);
    }

    public function send(Request $request, InfobipVerification $provider): RedirectResponse
    {
        abort_unless(InfobipVerification::enabled(), 404);
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/D'],
            'invite' => ['nullable', 'string', 'size:64'],
        ]);
        $phone = $data['phone'];
        $invite = $this->invitation($data['invite'] ?? null);
        if (! Cache::add('phone-otp:'.hash('sha256', $phone), true, 60)) {
            throw ValidationException::withMessages(['phone' => __('Wait a minute before requesting another code.')]);
        }
        $request->session()->forget('phone_challenge');
        $user = User::where('phone', $phone)->whereNotNull('phone_verified_at')->first();
        $eligible = $invite ? $invite->phone === $phone : $user?->teams()->exists();
        $pinId = null;
        if ($phone === config('services.phone_login.allowed_number') && $eligible) {
            $pinId = $provider->send($phone);
        }
        $request->session()->put('phone_challenge', [
            'phone' => $phone, 'pin' => $pinId, 'user' => $user?->id, 'invite' => $invite?->code,
            'expires' => now()->addMinutes(5)->timestamp,
        ]);

        return to_route('phone-login.show', array_filter(['invite' => $invite?->code]));
    }

    public function verify(Request $request, InfobipVerification $provider, AcceptTeamInvitation $accept): LoginResponse|RedirectResponse
    {
        abort_unless(InfobipVerification::enabled(), 404);
        $challenge = $request->session()->get('phone_challenge');
        $data = $request->validate([
            'otp' => ['required', 'string', 'regex:/^[0-9]{6}$/D'],
            'name' => [($challenge['invite'] ?? null) ? 'required' : 'nullable', 'string', 'max:255'],
        ]);
        if (! $challenge || ! $challenge['pin'] || $challenge['expires'] <= now()->timestamp
            || $challenge['phone'] !== config('services.phone_login.allowed_number')
            || ! $provider->verify($challenge['pin'], $data['otp'])) {
            throw ValidationException::withMessages(['otp' => __('The code is incorrect or expired. You can request a new one.')]);
        }
        $request->session()->forget('phone_challenge');

        $user = DB::transaction(function () use ($challenge, $data, $accept, $request): User {
            $invite = $this->invitation($challenge['invite'], lock: true);
            $user = User::where('phone', $challenge['phone'])->whereNotNull('phone_verified_at')->lockForUpdate()->first();
            abort_unless($user?->id === $challenge['user'], 403);
            if ($invite) {
                abort_unless($invite->phone === $challenge['phone'], 403);
                if (! $user) {
                    $user = new User(['name' => $data['name'], 'password' => Str::random(64)]);
                    $user->phone = $challenge['phone'];
                    $user->phone_verified_at = now();
                    $user->save();
                }
                if ($user->hasEnabledTwoFactorAuthentication()) {
                    $request->session()->put('phone_invite_after_2fa', ['code' => $invite->code, 'user_id' => $user->id]);
                } else {
                    $accept->handle($invite, $user);
                }
            }
            abort_unless($user && $user->teams()->exists(), 403);

            return $user;
        });
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->id, 'login.remember' => false]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return to_route('two-factor.login');
        }
        Auth::login($user);
        $request->session()->regenerate();

        return app(LoginResponse::class);
    }

    private function invitation(mixed $code, bool $lock = false): ?TeamInvitation
    {
        if ($code === null) {
            return null;
        }

        abort_unless(is_string($code), 404);
        $invite = TeamInvitation::where('code', $code)->whereNotNull('phone')->whereHas('team')
            ->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
        abort_unless($invite->isPending(), 410);

        return $invite;
    }
}
