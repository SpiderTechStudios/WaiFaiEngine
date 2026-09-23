<?php

return [
    /*
    | Pending captive portal session lifetime in minutes.
    */
    'session_ttl_minutes' => (int) env('CAPTIVE_SESSION_TTL', 30),

    /*
    | Always send WiFiDog /login browsers to THIS backend's /connect page
    | ({APP_URL}/connect), not a separate frontend. Set to false only when
    | an external CAPTIVE_PORTAL_URL must be used.
    */
    'force_backend_portal' => filter_var(
        env('CAPTIVE_FORCE_BACKEND_PORTAL', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | Absolute customer-facing captive portal URL (no query string).
    |
    | Used only when force_backend_portal is false. Prefer leaving the force
    | flag on so Ruijie always lands on the Blade portal in this app.
    |
    | NEVER set this to the WiFiDog API endpoint (/api/wifidog/login).
    */
    'portal_url' => env(
        'CAPTIVE_PORTAL_URL',
        rtrim((string) env('APP_URL', 'http://localhost'), '/').'/connect'
    ),

    /*
    | Where the WiFiDog gateway sends the browser after a successful auth
    | (protocol: AuthServer portal/ script). We redirect to the originally
    | requested URL, falling back to this absolute http(s) URL when the
    | captive session has no usable requested_url.
    */
    'portal_success_url' => env('CAPTIVE_PORTAL_SUCCESS_URL', 'http://www.google.com'),

    /*
    | Origin fallback when CAPTIVE_PORTAL_URL is empty / origin-only.
    | Defaults to APP_URL (backend), not FRONTEND_URL.
    */
    'portal_origin' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),

    /*
    | Path appended when portal_url / origin does not already end with it.
    */
    'portal_connect_path' => '/'.ltrim((string) env('CAPTIVE_PORTAL_CONNECT_PATH', '/connect'), '/'),

    /*
    | @deprecated Use portal_url / portal_origin. Kept for older config caches.
    */
    'portal_base_url' => rtrim((string) env(
        'CAPTIVE_PORTAL_URL',
        rtrim((string) env('APP_URL', 'http://localhost'), '/').'/connect'
    ), '/'),
];
