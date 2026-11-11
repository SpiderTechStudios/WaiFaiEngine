Access Subscription Lifecycle (Voucher & Paid Hotspot Users)
============================================================

This document describes the enforced behaviour for customer internet access
purchased by voucher or mobile-money payment.


1. When access starts
---------------------
An `access_grants` row is created when the customer actually gains access:

- Paid portal payment: `NetworkSessionService::resolveAccessGrant()` creates
  the grant when the hotspot session starts.
- Voucher redemption: `VoucherService::performConsume()` creates the grant at
  redemption time.

In both cases:

  starts_at = now()
  expires_at = starts_at + plan duration (HOURS/DAYS/WEEKS/MONTHS)

Source: `app/Services/NetworkSessionService.php` (`expiresAtForPlan`) and
`app/Services/VoucherService.php` (`expiresAtForPlan`).

Example: a 24-hour package bought at 10:00 on day 1 expires at 10:00 on day 2.
Access is a fixed wall-clock window. It is NOT usage time and does not pause
while the customer is offline.


2. Online while connected
-------------------------
A successful payment/voucher creates a live `network_sessions` row with
status `active` and a `captive_sessions` row with status `authenticated`.
That is what makes the customer "online".

Access is **time-based, never activity-based**. Once a payment or voucher
creates an active access grant, the client keeps access until that grant
expires. A phone sleeping, Wi-Fi power saving, a brief drop, or a missed
counter/poll must not disconnect a paid customer.

MikroTik:
- `mikrotik:sync` runs every minute and records active hotspot clients.
- A session missing from `/ip/hotspot/active` no longer ends the network
  session or disconnects the captive session. The router-side hotspot user is
  also kept while the grant is usable.

WiFiDog (Ruijie, Wavlink, etc.):
- The gateway sends `/api/wifidog/auth?stage=counters` while the client is
  connected. `WiFiDogService::recordCounters()` updates
  `network_sessions.last_activity_at`.
- `access:presence` is a legacy no-op. It no longer ends sessions or marks
  captive sessions disconnected.

Sessions that were already marked `disconnected` by the old presence logic are
recovered automatically when the gateway next calls `/api/wifidog/auth` (or
when the client returns to `/api/wifidog/login`), provided the grant is still
usable. Access is only removed by `access:expire` at the grant's `expires_at`.


3. Returning before expiry (online again)
-----------------------------------------
`CaptiveSessionService::resumeForReturningDevice()` looks for a usable grant
for the device MAC (directly on the session or through earlier network
sessions). If the grant is still usable:

- a new network session is created/activated,
- the captive session becomes `authenticated` again,
- the portal sends the device back to the gateway (`/wifidog/auth?token=...`).

Result: online again, still using the SAME grant/expiry. No extra time is
added.


4. Returning after expiry (denied)
----------------------------------
`AccessGrant::isUsable()` requires:

  status = active AND (expires_at IS NULL OR expires_at > now())

If the customer comes back after the fixed expiry:

- `resumeForReturningDevice()` returns `null` (no gateway auth),
- WiFiDog `/auth` returns `Auth:0`,
- `access:expire` marks the grant `expired`, network sessions ended,
  captive sessions expired,
- `RevokeGatewaySession` sends the logout/kick to the router.

This happens even if the customer only used a few minutes of the paid window.
There is no usage-based refund or extension.

Example: 24h bought at 10:00 day 1, customer leaves after 20 minutes and
returns at 10:01 day 2 -> no access; the grant expires at 10:00 day 2.

Unlimited packages (`duration_unit = UNLIMITED_DATA`) have `expires_at = null`
and do not expire on time.


5. Scheduled enforcement
------------------------
`routes/console.php`:

  every minute  access:expire     expiry + router logout/kick (only at grant expiry)
  every minute  mikrotik:sync     MikroTik heartbeat + active users

Manual commands:

  php artisan access:expire
  php artisan access:presence    (legacy no-op)
  php artisan mikrotik:sync


6. Router revocation
--------------------
`RevokeGatewaySession` dispatches `GatewayAccessRevoker`:

- MikroTik: removes the active hotspot session, hotspot user and cookie.
- Ruijie / Wavlink (WiFiDog): calls the gateway directly:
    GET http://{gw_address}:{gw_port}/wifidog/auth?logout=1&token={token}
  (with `/wifidog/auth/` fallback).
- Unsupported gateway types are logged as
  `access.revoke_unsupported_gateway`.

Note: WiFiDog logout requires the backend to reach the gateway LAN IP. If the
gateway is behind NAT and unreachable, the logout call fails but the gateway
will still deny the client with `Auth:0` on its next auth check.

Supported `gateway_type` values are `mikrotik`, `ruijie`, `wavlink` and
`omada`. Omada uses its own session table (`omada_client_sessions`) and
authorization endpoint; see `docs/omada-integration.md`.


7. Configuration
----------------
  CAPTIVE_PRESENCE_TIMEOUT_MINUTES=5   # deprecated / no-op
  MIKROTIK_MISSING_SESSION_GRACE=120   # deprecated / no-op

  config/captive.php
    'presence_timeout_minutes' => env('CAPTIVE_PRESENCE_TIMEOUT_MINUTES', 5)

  config/mikrotik.php
    'missing_session_grace_seconds' (env MIKROTIK_MISSING_SESSION_GRACE)

  Access is controlled by the access grant's `expires_at`, not by these values.


8. Data model
-------------
  access_grants
    starts_at, expires_at, status, source (voucher|payment)
  network_sessions
    status (active|ended), started_at, ended_at, last_activity_at
  captive_sessions
    status (pending|authenticated|disconnected|expired|rejected),
    authenticated_at, expires_at, last_seen_at

The grant is the source of truth for "is this customer entitled to access?".
Sessions represent presence only. When an active session ends, the grant
survives until `expires_at` so the customer can return and resume.

Important: a company's platform subscription (WaiFai billing) is a separate
concept from a customer's hotspot plan and is not handled by these rules.
