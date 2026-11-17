<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | BullMQ task service (../queue). Core dispatches over HTTP and receives
    | signed lifecycle webhooks at the `webhooks.queue` route.
    */
    'queue' => [
        'url' => env('QUEUE_SERVICE_URL', 'http://127.0.0.1:3100'),
        'token' => env('QUEUE_SERVICE_TOKEN'),
        'webhook_secret' => env('QUEUE_SERVICE_WEBHOOK_SECRET'),
        // Override when the queue service reaches Core on a different host than APP_URL.
        'callback_url' => env('QUEUE_SERVICE_CALLBACK_URL'),
        'default_queue' => env('QUEUE_SERVICE_DEFAULT_QUEUE', 'default'),
        'timeout' => (float) env('QUEUE_SERVICE_TIMEOUT', 5),
        'connect_timeout' => (float) env('QUEUE_SERVICE_CONNECT_TIMEOUT', 2),
        'signature_tolerance' => (int) env('QUEUE_SERVICE_SIGNATURE_TOLERANCE', 300),
    ],

];
