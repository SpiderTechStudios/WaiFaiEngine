<?php

return [
    /*
    | Pending captive portal session lifetime in minutes.
    */
    'session_ttl_minutes' => (int) env('CAPTIVE_SESSION_TTL', 10),

    /*
    | Absolute customer-facing captive portal URL (no query string).
    |
    | Preferred (full connect page):
    |   CAPTIVE_PORTAL_URL=https://waifai.shereheyangu.com/connect
    |
    | Also accepted (origin only — connect path is appended):
    |   CAPTIVE_PORTAL_URL=https://waifai.shereheyangu.com
    |   CAPTIVE_PORTAL_CONNECT_PATH=/connect
    |
    | NEVER set this to the WiFiDog API endpoint
    |   (/api/wifidog/login) — that produces /api/wifidog/login/login.
    */
    'portal_url' => env('CAPTIVE_PORTAL_URL'),

    /*
    | Origin fallback when CAPTIVE_PORTAL_URL is empty.
    */
    'portal_origin' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

    /*
    | Path appended when portal_url / origin does not already end with it.
    */
    'portal_connect_path' => '/'.ltrim((string) env('CAPTIVE_PORTAL_CONNECT_PATH', '/connect'), '/'),

    /*
    | @deprecated Use portal_url / portal_origin. Kept for older config caches.
    */
    'portal_base_url' => rtrim((string) env('CAPTIVE_PORTAL_URL', env('FRONTEND_URL', env('APP_URL', 'http://localhost'))), '/'),
];
