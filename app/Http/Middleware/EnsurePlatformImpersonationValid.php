<?php

namespace App\Http\Middleware;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\PlatformRole;
use App\Models\User;
use App\Support\PlatformImpersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformImpersonationValid
{
    /**
     * Validate any active platform impersonation session and handle expiry or revocation.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has(PlatformImpersonation::SessionKey)) {
            return $next($request);
        }

        $context = PlatformImpersonation::context($request);

        if ($context === null) {
            $request->session()->forget(PlatformImpersonation::SessionKey);

            return $next($request);
        }

        $impersonator = User::find($context['impersonator_id']);

        if (! $impersonator instanceof User || $impersonator->platform_role !== PlatformRole::SuperAdmin) {
            $request->session()->forget(PlatformImpersonation::SessionKey);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login');
        }

        if ($context['expires_at'] <= now()->timestamp) {
            return app(PlatformImpersonation::class)->stop(
                $request,
                $impersonator,
                $context,
                app(RecordAuditEvent::class),
                'expired',
            );
        }

        return $next($request);
    }
}
