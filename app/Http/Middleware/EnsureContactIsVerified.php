<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContactIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->email === null && $user->phone_verified_at !== null) {
            return $next($request);
        }

        return (new EnsureEmailIsVerified)->handle($request, $next);
    }
}
