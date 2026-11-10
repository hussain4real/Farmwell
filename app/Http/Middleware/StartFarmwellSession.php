<?php

namespace App\Http\Middleware;

use App\Support\PilotPhone;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

class StartFarmwellSession extends StartSession
{
    public function __construct(SessionManager $manager, CacheFactory $cache)
    {
        parent::__construct($manager, fn (): CacheFactory => $cache);
    }

    public function getSession(Request $request): Session
    {
        $name = (string) config('session.cookie');
        $this->manager->driver()->setName(PilotPhone::isPilot($request) ? $name.'_pilot' : $name);

        return parent::getSession($request);
    }

    protected function addCookieToResponse(Response $response, Session $session): void
    {
        parent::addCookieToResponse($response, $session);

        if (config('session.driver') !== 'database' || config('session.expire_on_close') || request()->user() === null) {
            return;
        }

        $expires = Date::now()->addMinutes((int) config('session.authenticated_lifetime'));

        foreach ($response->headers->getCookies() as $cookie) {
            if (in_array($cookie->getName(), [$session->getName(), 'XSRF-TOKEN'], true)) {
                $response->headers->setCookie($cookie->withExpires($expires));
            }
        }
    }
}
