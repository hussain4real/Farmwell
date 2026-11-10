<?php

namespace App\Support;

use Illuminate\Session\DatabaseSessionHandler;

class PilotDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function expired(mixed $session): bool
    {
        if (isset($session->user_id, $session->last_activity) && PilotPhone::isPilot(request())) {
            return $session->last_activity < $this->currentTime() - PilotPhone::SessionLifetimeMinutes * 60;
        }

        return parent::expired($session);
    }

    public function gc(mixed $lifetime): int
    {
        // Both hosts share the session table, so cleanup must allow for the longer pilot lifetime.
        return parent::gc(max($lifetime, PilotPhone::SessionLifetimeMinutes * 60));
    }
}
