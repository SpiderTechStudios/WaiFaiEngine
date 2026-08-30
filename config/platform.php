<?php

return [
    'currency' => env('PLATFORM_CURRENCY', 'TZS'),

    'subscription_monthly' => (int) env('PLATFORM_SUBSCRIPTION_MONTHLY', 10000),

    'subscription_grace_days' => (int) env('PLATFORM_SUBSCRIPTION_GRACE_DAYS', 7),

    'installation' => [
        'installation_only' => (int) env('PLATFORM_INSTALLATION_ONLY', 100000),
        'router_and_installation' => (int) env('PLATFORM_ROUTER_AND_INSTALLATION', 150000),
    ],

    'signup_intent_ttl_hours' => (int) env('PLATFORM_SIGNUP_INTENT_TTL_HOURS', 48),

    /*
    | When true, newly created platform payments are marked paid immediately.
    | Use for local/tests only until a real mobile-money gateway is wired.
    */
    'payment_auto_paid' => (bool) env('PLATFORM_PAYMENT_AUTO_PAID', false),

    /*
    | When true, POST /auth/register still creates accounts without payment (local/dev).
    */
    'allow_free_register' => (bool) env('PLATFORM_ALLOW_FREE_REGISTER', false),
];
