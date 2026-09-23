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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ruijie' => [
        'base_url' => env('RUIJIE_CLOUD_BASE_URL', 'https://cloudapi.ruijienetworks.com'),
        'timeout' => (int) env('RUIJIE_CLOUD_TIMEOUT', 15),
    ],

    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY', 'FLWPUBK-274a977b8810f00b9992d4ff70846b7b-X'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
        'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com'),
    ],

    'palmpay' => [
        'app_id' => env('PALMPAY_APP_ID'),
        'private_key' => env('PALMPAY_PRIVATE_KEY'),
        'public_key' => env('PALMPAY_PUBLIC_KEY'),
        'country_code' => env('PALMPAY_COUNTRY_CODE', 'NG'),
        'version' => env('PALMPAY_VERSION', 'V2'),
        'base_url' => env('PALMPAY_BASE_URL', 'https://open-gw-prod.palmpay-inc.com'),
    ],

    'palmpesa' => [
        'api_token' => env('PALMPESA_API_TOKEN'),
        'user_id' => env('PALMPESA_USER_ID'),
        'base_url' => env('PALMPESA_BASE_URL', 'https://palmpesa.drmlelwa.co.tz'),
        'status_check_minutes' => (int) env('PALMPESA_STATUS_CHECK_MINUTES', 4),
        'status_timeout' => (int) env('PALMPESA_STATUS_TIMEOUT', 8),
        // How often a captive-portal payment poll may call provider order-status.
        'portal_reconcile_seconds' => (int) env('PALMPESA_PORTAL_RECONCILE_SECONDS', 20),
        'callback_url' => env('PALMPESA_CALLBACK_URL'),
        'vendor' => env('PALMPESA_VENDOR', 'TILL61103867'),
    ],

];
