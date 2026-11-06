<?php

namespace App\Support;

class PhoneCallingCode
{
    public static function forRecipient(?string $phone = null): ?string
    {
        $phone ??= config('services.phone_login.allowed_number');
        if (! is_string($phone) || ! preg_match('/^\+[1-9]\d{7,14}$/D', $phone)) {
            return null;
        }

        // E.164's one- and two-digit codes are prefix-free. Other assigned codes use three digits.
        preg_match('/^\+(?:[17]|2[07]|3[0-469]|4[013-9]|5[1-8]|6[0-6]|8[1246]|9[0-58]|[0-9]{3})/', $phone, $match);

        return $match[0];
    }
}
