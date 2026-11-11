<?php

return [
    /*
    | RouterOS REST request timeout in seconds.
    */
    'timeout' => (int) env('MIKROTIK_TIMEOUT', 10),

    /*
    | Verify the router's TLS certificate. Keep this enabled for production.
    */
    'verify_tls' => filter_var(
        env('MIKROTIK_VERIFY_TLS', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | Default HTTP port of the hotspot login page (RouterOS usually 80).
    */
    'hotspot_port' => (int) env('MIKROTIK_HOTSPOT_PORT', 80),

    /*
    | Scheme of the hotspot login page. RouterOS hotspot login is HTTP by
    | default. Change to https only if the hotspot profile uses login-by=https.
    */
    'hotspot_scheme' => env('MIKROTIK_HOTSPOT_SCHEME', 'http'),

    /*
    | Public key used in the hotspot login redirect. It scopes the router; it
    | is not a replacement for the payment/session token.
    */
    'portal_key_length' => 48,

    /*
    | Secret used by the optional router on-logout/on-login callback.
    */
    'callback_secret_length' => 48,

    /*
    | DEPRECATED / no-op. Missing from /ip hotspot active no longer ends a
    | session or disconnects the captive session; the access grant owns access
    | until expiry. Kept for config compatibility.
    */
    'missing_session_grace_seconds' => (int) env('MIKROTIK_MISSING_SESSION_GRACE', 120),
];
