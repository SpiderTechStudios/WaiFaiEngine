<?php

return [
    /*
    | Pending captive portal session lifetime in minutes.
    */
    'session_ttl_minutes' => (int) env('CAPTIVE_SESSION_TTL', 30),

    /*
    | Absolute customer-facing captive portal URL (no query string).
    |
    | The captive portal is rendered by this same backend app (see
    | routes/web.php -> CaptivePortalController), so it defaults to
    | {APP_URL}/connect. Override with CAPTIVE_PORTAL_URL when the portal
    | lives elsewhere.
    |
    | The WiFiDog login redirect becomes:
    |   {CAPTIVE_PORTAL_URL}?subdomain={company.subdomain}&router={router}&session={token}
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
    */
    'portal_origin' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

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
