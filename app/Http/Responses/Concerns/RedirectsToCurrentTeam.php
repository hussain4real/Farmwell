<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam($request, string $redirect): string
    {
        $team = $this->currentTeam($request);

        URL::defaults(['current_team' => $team->slug]);

        $user = $request->user();
        if ($redirect === '/dashboard' && $user?->can('viewInvestorPortal', $team) && ! $user->can('viewFarmOperations', $team)) {
            $intended = $request->session()->get('url.intended');
            if (is_string($intended) && parse_url($intended, PHP_URL_PATH) !== "/{$team->slug}/investor-portal") {
                $request->session()->forget('url.intended');
            }

            return "/{$team->slug}/investor-portal";
        }

        return "/{$team->slug}{$redirect}";
    }

    protected function currentTeam($request)
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->personalTeam();

        if (! $team) {
            abort(403);
        }

        return $team;
    }
}
