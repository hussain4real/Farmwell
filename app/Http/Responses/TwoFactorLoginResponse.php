<?php

namespace App\Http\Responses;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use App\Models\TeamInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    use RedirectsToCurrentTeam;

    public function toResponse($request): Response
    {
        $pending = $request->session()->pull('phone_invite_after_2fa');
        if ($pending && $pending['user_id'] === $request->user()->id) {
            DB::transaction(function () use ($pending, $request): void {
                $invite = TeamInvitation::where('code', $pending['code'])->where('phone', $request->user()->phone)
                    ->whereNotNull('phone')->whereHas('team')->lockForUpdate()->firstOrFail();
                abort_unless($invite->isPending(), 410);
                app(AcceptTeamInvitation::class)->handle($invite, $request->user());
            });
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended($this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));
    }
}
