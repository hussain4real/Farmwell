<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Support\PilotPhone;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePilotPhoneAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PilotPhone::isPilot($request) || config('services.phone_login.full_workspace')) {
            return $next($request);
        }

        if ($request->routeIs('home', 'login')) {
            return to_route('phone-login.show');
        }
        if ($request->routeIs('phone-login.*', 'logout')) {
            return $next($request);
        }
        if ($request->routeIs('two-factor.login', 'two-factor.login.store')) {
            abort_unless($request->session()->has('pilot_phone_user') && $request->session()->get('pilot_phone_user') === $request->session()->get('login.id'), 403);

            return $next($request);
        }

        $team = $request->route('current_team');
        abort_unless($team, 404);
        $user = $request->user();
        abort_unless($user && $user->phone_verified_at && $user->id === $request->session()->get('pilot_phone_user'), 403);
        abort_unless($user->phoneInvitedTeams()->where('slug', $team instanceof Team ? $team->slug : $team)->exists(), 403);

        return $next($request);
    }
}
