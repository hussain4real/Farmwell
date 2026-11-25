<?php

use App\Http\Middleware\EnsurePilotPhoneAccess;
use App\Http\Middleware\EnsurePlatformImpersonationValid;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use App\Http\Middleware\SignalQueuedWork;
use App\Http\Middleware\StartFarmwellSession;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->append(SignalQueuedWork::class);

        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();
            abort_unless($user instanceof User, 403);

            $team = $user->currentTeam ?? $user->personalTeam();
            abort_unless($team, 403);

            $route = $user->can('viewInvestorPortal', $team) && $user->cannot('viewFieldWork', $team)
                ? 'investor-portal.index'
                : 'dashboard';

            return route($route, ['current_team' => $team]);
        });

        $middleware->web(append: [
            EnsurePilotPhoneAccess::class,
            EnsurePlatformImpersonationValid::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
        ], replace: [StartSession::class => StartFarmwellSession::class]);

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['otp']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*')
                || ($exception instanceof ValidationException && $request->routeIs('login.store') && $request->wantsJson()),
        );
    })->create();
