<?php

namespace App\Http\Middleware;

use App\Support\PlatformAccess;
use App\Support\UserTeam;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'canManagePlatform' => fn () => $user?->can('managePlatform') ?? false,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'platformAccess' => fn () => PlatformAccess::context($request),
            'currentTeam' => function () use ($request, $user) {
                $team = $request->attributes->get('platform_access_team');

                return $team ? new UserTeam($team->id, $team->name, $team->slug, $team->is_personal, null, 'Super Admin · read only', true)
                    : ($user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null);
            },
            'currentTeamPermissions' => function () use ($request, $user) {
                $team = $request->attributes->get('platform_access_team') ?? $user?->currentTeam;

                return $team ? $user->toTeamPermissions($team) : null;
            },
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
        ];
    }
}
