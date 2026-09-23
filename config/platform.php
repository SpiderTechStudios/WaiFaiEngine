<?php

return [
    'currency' => env('PLATFORM_CURRENCY', 'TZS'),

    'subscription_monthly' => (int) env('PLATFORM_SUBSCRIPTION_MONTHLY', 10000),

    'subscription_grace_days' => (int) env('PLATFORM_SUBSCRIPTION_GRACE_DAYS', 7),

    'installation' => [
        'installation_only' => (int) env('PLATFORM_INSTALLATION_ONLY', 100000),
        'router_and_installation' => (int) env('PLATFORM_ROUTER_AND_INSTALLATION', 150000),
    ],

    /*
    | Temporary enrollment (pay-first registration) lifetime in minutes.
    */
    'enrollment_ttl_minutes' => (int) env('PLATFORM_ENROLLMENT_TTL_MINUTES', 4),

    /*
    | How long after an enrollment expires a provider-confirmed payment may still
    | create the account. Mobile-money approvals often land after the short
    | reservation TTL; without this window a successful payment would be lost.
    */
    'enrollment_payment_grace_minutes' => (int) env('PLATFORM_ENROLLMENT_PAYMENT_GRACE_MINUTES', 60),

    /*
    | Maximum failed platform-subscription payment attempts per enrollment.
    */
    'enrollment_max_failed_attempts' => (int) env('PLATFORM_ENROLLMENT_MAX_FAILED_ATTEMPTS', 3),

    /*
    | Shared secret for platform payment provider webhooks.
    | Send as X-Platform-Payment-Secret header.
    */
    'payment_webhook_secret' => env('PLATFORM_PAYMENT_WEBHOOK_SECRET'),

    /*
    | When true, newly created platform payments are marked paid immediately.
    | Use for local/tests only until a real mobile-money gateway is wired.
    | Paid enrollment payments also auto-complete account creation.
    */
    'payment_auto_paid' => (bool) env('PLATFORM_PAYMENT_AUTO_PAID', false),
];
