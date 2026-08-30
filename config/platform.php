<?php

return [
    'currency' => env('PLATFORM_CURRENCY', 'TZS'),

    'subscription_monthly' => (int) env('PLATFORM_SUBSCRIPTION_MONTHLY', 10000),

    'signup_intent_ttl_hours' => (int) env('PLATFORM_SIGNUP_INTENT_TTL_HOURS', 48),

    /*
    | When true, newly created platform signup payments are marked paid immediately.
    | Use for local/tests only until a real mobile-money gateway is wired.
    */
    'payment_auto_paid' => (bool) env('PLATFORM_PAYMENT_AUTO_PAID', false),

    /*
    | When true, POST /auth/register still creates accounts without payment (local/dev).
    | Production should leave this false so public signup uses pay-first flow.
    */
    'allow_free_register' => (bool) env('PLATFORM_ALLOW_FREE_REGISTER', false),
];
