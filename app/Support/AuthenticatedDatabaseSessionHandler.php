<?php

namespace App\Support;

use Illuminate\Session\DatabaseSessionHandler;

class AuthenticatedDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function expired(mixed $session): bool
    {
        if (isset($session->user_id, $session->last_activity)) {
            return $session->last_activity < $this->currentTime() - (int) config('session.authenticated_lifetime') * 60;
        }

        return parent::expired($session);
    }

    public function gc(mixed $lifetime): int
    {
        // Guests and authenticated users share the table and have different idle limits.
        return parent::gc(max($lifetime, (int) config('session.authenticated_lifetime') * 60));
    }
}
