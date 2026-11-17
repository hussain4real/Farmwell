<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SignalQueuedWork
{
    public const string Attribute = 'farmwell.queued_work';

    public const string Header = 'X-Farmwell-Wake-Queue';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->attributes->getBoolean(self::Attribute)) {
            $response->headers->set(self::Header, '1');
        }

        return $response;
    }
}
