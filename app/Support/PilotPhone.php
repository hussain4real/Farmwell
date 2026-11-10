<?php

namespace App\Support;

use App\Models\TeamInvitation;
use Illuminate\Http\Request;

class PilotPhone
{
    public const SessionLifetimeMinutes = 14 * 24 * 60;

    public const Countries = ['NG' => '+234', 'QA' => '+974'];

    public const NumberPattern = '/^\+(?:234[1-9][0-9]{9}|974[1-9][0-9]{7})$/D';

    public static function isPilot(Request $request): bool
    {
        return $request->getHost() === config('services.phone_login.pilot_host');
    }

    public static function invitationUrl(TeamInvitation $invite): string
    {
        $host = config('services.phone_login.pilot_host');

        return $host ? 'https://'.$host.route('phone-login.show', ['invite' => $invite->code], false) : route('phone-login.show', ['invite' => $invite->code]);
    }

    public static function callingCode(Request $request): string
    {
        foreach ([$request->query('country'), $request->cookie('phone_country'), $request->header('CF-IPCountry')] as $country) {
            if (is_string($country) && isset(self::Countries[$country])) {
                return self::Countries[$country];
            }
        }

        return self::Countries['NG'];
    }
}
