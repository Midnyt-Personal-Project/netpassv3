<?php

return [

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

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        // Keep this aligned with your Paystack contract; it is used for payout reporting.
        'fee_percentage' => (float) env('PAYSTACK_FEE_PERCENTAGE', 1.95),
    ],

    'arkesel' => [
        'api_key' => env('ARKESEL_SMS_API_KEY'),
        'sender_id' => env('ARKESEL_SMS_SENDER_ID', 'OyaloWiFi'),
        // When true, numbers are tried in local format (0244xxxxxx) first,
        // then automatically retried in international format (233xxxxxxxx).
        'local_format' => (bool) env('ARKESEL_SMS_LOCAL_FORMAT', true),
        // Max SMS sent synchronously inside one "send now" announcement
        // request. Everything beyond this is finished by the scheduler.
        'inline_blast_limit' => (int) env('ARKESEL_SMS_INLINE_BLAST_LIMIT', 1000),
    ],

    'router' => [
        'offline_after_minutes' => (int) env('ROUTER_OFFLINE_AFTER_MINUTES', 3),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
