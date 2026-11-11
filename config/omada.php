<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TP-Link Omada integration
    |--------------------------------------------------------------------------
    |
    | Platform-wide settings only. Each customer's controller URL, credentials,
    | sites and SSIDs are stored encrypted in the omada_* tables, never here.
    |
    */

    'enabled' => filter_var(env('OMADA_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'timeout' => (int) env('OMADA_API_TIMEOUT', 15),

    'connect_timeout' => (int) env('OMADA_CONNECT_TIMEOUT', 8),

    'retries' => (int) env('OMADA_CONNECTION_RETRIES', 2),

    'token_cache_ttl' => (int) env('OMADA_TOKEN_CACHE_TTL', 300),

    'session_sync_interval' => (int) env('OMADA_SESSION_SYNC_INTERVAL', 5),

    'queue_connection' => env('OMADA_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

    /*
    | Verify controller TLS certificates. Keep true in production. A customer
    | may opt out per integration only when allow_self_signed is true.
    */
    'verify_tls' => filter_var(env('OMADA_VERIFY_TLS', true), FILTER_VALIDATE_BOOLEAN),

    'allow_self_signed' => filter_var(env('OMADA_ALLOW_SELF_SIGNED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | SSRF protection. Private/loopback/link-local/reserved addresses and cloud
    | metadata endpoints are rejected unless the integration explicitly uses a
    | private-network connection method and this flag allows it.
    */
    'allow_private_hosts' => filter_var(env('OMADA_ALLOW_PRIVATE_HOSTS', false), FILTER_VALIDATE_BOOLEAN),

    'allow_insecure_http' => filter_var(env('OMADA_ALLOW_INSECURE_HTTP', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Default Omada controller HTTPS management ports. The customer can override
    | the port; 8043 is the software-controller default and 443 the OC default.
    */
    'default_port' => (int) env('OMADA_DEFAULT_PORT', 8043),

    'page_size' => (int) env('OMADA_PAGE_SIZE', 1000),

    'portal_key_length' => 48,

    /*
    | Legacy external-portal authorization defaults. authType 4 is the Omada
    | external portal auth type. The duration is derived from the package.
    */
    'auth_type' => 4,

    'max_auth_duration_seconds' => (int) env('OMADA_MAX_AUTH_DURATION_SECONDS', 86400),
];
