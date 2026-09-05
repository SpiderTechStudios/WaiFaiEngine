<?php

return [
    /*
    | Pending captive portal session lifetime in minutes.
    */
    'session_ttl_minutes' => (int) env('CAPTIVE_SESSION_TTL', 10),

    /*
    | Customer-facing captive portal base URL (no trailing slash).
    | Login redirects to: {portal_base_url}/connect?subdomain=...&session=...
    */
    'portal_base_url' => rtrim((string) env('CAPTIVE_PORTAL_URL', env('FRONTEND_URL', env('APP_URL', 'http://localhost'))), '/'),

    /*
    | Path on the frontend where the captive UI lives.
    */
    'portal_connect_path' => env('CAPTIVE_PORTAL_CONNECT_PATH', '/connect'),
];
