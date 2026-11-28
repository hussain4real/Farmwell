<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class KudiSmsVerification
{
    public const LifetimeMinutes = 10;

    public function send(string $phone): string
    {
        $sender = config('services.kudisms.sender');
        $application = config('services.kudisms.application_code');
        $template = config('services.kudisms.template_code');
        if (! $sender || ! $application || ! $template) {
            throw $this->unavailable();
        }

        $result = $this->post('sendotp', [
            'senderID' => $sender,
            'recipients' => substr($phone, 1),
            'appnamecode' => $application,
            'templatecode' => $template,
            'otp_type' => 'NUMERIC',
            'otp_length' => 6,
            'otp_duration' => self::LifetimeMinutes,
            'otp_attempts' => 2,
            'channel' => 'sms',
        ]);
        $id = $result['verification_id'] ?? null;
        if (! $this->succeeded($result) || ! is_string($id) || $id === '') {
            throw $this->unavailable();
        }

        return $id;
    }

    public function verify(string $id, string $code): bool
    {
        $result = $this->post('verifyotp', ['verification_id' => $id, 'otp' => $code]);

        return $this->succeeded($result) && ($result['verification_id'] ?? null) === $id;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function succeeded(array $result): bool
    {
        return ($result['status'] ?? null) === 'success' && ($result['error_code'] ?? null) === '000';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $token = config('services.kudisms.key');
        if (! $token) {
            throw $this->unavailable();
        }

        try {
            $response = Http::asMultipart()->acceptJson()->withoutRedirecting()
                ->connectTimeout(3)->timeout(8)->post('https://my.kudisms.net/api/'.$path, ['token' => $token, ...$payload]);
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
