<?php

return [
    'phone_login' => [
        'enabled' => env('PHONE_LOGIN_ENABLED', false),
        'provider' => env('PHONE_OTP_PROVIDER', 'infobip'),
        'allowed_number' => env('PHONE_OTP_ALLOWED_NUMBER'),
        'pilot_host' => env('PHONE_PILOT_HOST'),
        'full_workspace' => env('PHONE_PILOT_FULL_WORKSPACE', false),
        'pilot_sms_daily_limit' => (int) env('PHONE_PILOT_SMS_DAILY_LIMIT', 5),
        'nigerian_pilot_provider' => env('PHONE_PILOT_NIGERIA_OTP_PROVIDER', 'infobip'),
    ],
    'infobip' => [
        'base_url' => env('INFOBIP_BASE_URL'),
        'key' => env('INFOBIP_API_KEY'),
        'application_id' => env('INFOBIP_2FA_APP_ID'),
        'message_id' => env('INFOBIP_2FA_MESSAGE_ID'),
    ],
    'kudisms' => [
        'key' => env('KUDISMS_API_KEY'),
        'sender' => env('KUDISMS_SENDER_ID', 'Farmwell'),
        'application_code' => env('KUDISMS_OTP_APP_CODE'),
        'template_code' => env('KUDISMS_OTP_TEMPLATE_CODE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'key' => env('CLOUDFLARE_KEY'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
