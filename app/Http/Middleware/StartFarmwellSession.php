<?php

namespace App\Http\Middleware;

use App\Support\PilotPhone;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Date;

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

    protected function getCookieExpirationDate(): DateTimeInterface|int
    {
        if (config('session.driver') === 'database' && ! config('session.expire_on_close') && request()->user() !== null) {
            return Date::now()->addMinutes((int) config('session.authenticated_lifetime'));
        }

        return parent::getCookieExpirationDate();
    }
}
