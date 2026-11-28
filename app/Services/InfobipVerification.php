<?php

namespace App\Services;

use App\Support\PhoneCallingCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class InfobipVerification
{
    public static function enabled(): bool
    {
        return config('services.phone_login.enabled') && config('services.phone_login.provider') === 'infobip'
            && (config('services.phone_login.pilot_host') || PhoneCallingCode::forRecipient() !== null);
    }

    public function send(string $phone): string
    {
        $result = $this->post('pin?ncNeeded=false', [
            'to' => substr($phone, 1),
            'applicationId' => config('services.infobip.application_id'),
            'messageId' => config('services.infobip.message_id'),
        ]);
        if (($result['smsStatus'] ?? null) !== 'MESSAGE_SENT' || ! is_string($result['pinId'] ?? null) || $result['pinId'] === '') {
            throw $this->unavailable();
        }

        return $result['pinId'];
    }

    public function verify(string $id, string $code): bool
    {
        return ($this->post('pin/'.rawurlencode($id).'/verify', ['pin' => $code])['verified'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $base = rtrim((string) config('services.infobip.base_url'), '/');
        if (! preg_match('~^https://[a-z0-9-]+\.api\.infobip\.com$~i', $base)) {
            throw $this->unavailable();
        }

        try {
            $response = Http::withToken((string) config('services.infobip.key'), 'App')->acceptJson()
                ->withoutRedirecting()->connectTimeout(3)->timeout(8)->post($base.'/2fa/2/'.$path, $payload);
        } catch (ConnectionException) {
            throw $this->unavailable();
        }

        if (! $response->successful()) {
            throw $this->unavailable();
        }

        $result = $response->json();

        return is_array($result) ? $result : [];
    }

    private function unavailable(): ValidationException
    {
        return ValidationException::withMessages(['service' => __('Phone verification is unavailable. Please try again shortly.')]);
    }
}
