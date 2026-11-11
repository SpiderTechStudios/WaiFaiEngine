# TP-Link Omada integration

Omada is an **isolated** gateway integration. It does not change MikroTik, Ruijie/WiFiDog, payment or disbursement code. MikroTik and Ruijie keep their existing `gateway_type` values and services.

## What was added

| Area | Files |
| --- | --- |
| Migrations | `database/migrations/2026_11_11_10000*_create_omada_*_table.php` |
| Models | `app/Models/OmadaIntegration.php`, `OmadaSite.php`, `OmadaAccessPoint.php`, `OmadaSsid.php`, `OmadaClientSession.php` |
| Services | `app/Services/Omada/*` |
| API | `app/Http/Controllers/Api/V1/Operations/OmadaIntegrationController.php`, `OmadaPortalController.php` |
| Routes | `routes/Api/v1/omada.php`, `routes/Api/omada.php` |
| Config | `config/omada.php`, `.env.example` |
| Sync command | `app/Console/Commands/SyncOmadaCommand.php` (`omada:sync`, every five minutes) |

Shared-code changes are additive only:

- `NetworkDevice::GATEWAY_OMADA` + one entry in `GATEWAY_TYPES`.
- `StoreRouterRequest` accepts `gateway_type=omada` and optional `omada_*` fields.
- `RouterService::create/update/test/setup` has an `omada` branch; existing branches are unchanged.
- OpenAPI enums include `omada`.

## Supported deployment types

| Deployment | Connection | Authentication |
| --- | --- | --- |
| Software Controller (Windows/Linux/self-hosted) | Public HTTPS, VPN, or public DDNS | Open API client credentials |
| Hardware Controller (OC200/OC300) | Public HTTPS or VPN | Open API client credentials |
| Cloud Controller | Omada cloud management URL | Open API client credentials |

The integration uses the **Omada Open API**, not the internal web API:

1. `POST /openapi/authorize/token?grant_type=client_credentials` with `omadacId`, `client_id`, `client_secret`.
2. `Authorization: AccessToken=<token>` (the literal `AccessToken=` prefix, not `Bearer`).
3. `GET /openapi/v1/{omadacId}/sites?page=1&pageSize=1000`.
4. `GET /openapi/v1/{omadacId}/sites/{siteId}/devices?page=1&pageSize=1000` (APs are filtered from `type=ap`).
5. `GET /openapi/v1/{omadacId}/sites/{siteId}/wlans` with a fallback to `/setting/wlans` for controller versions that expose SSIDs only there.
6. `DELETE /openapi/v1/{omadacId}/sites/{siteId}/clients/{clientMac}` to disconnect a client.

External portal authorization uses the documented operator flow (controller 5.0.15+):

1. `POST /{omadacId}/api/v2/hotspot/login` with the Hotspot Operator username/password returns a CSRF token and session cookie.
2. `POST /{omadacId}/api/v2/hotspot/extPortal/auth` with `clientMac`, `apMac`, `ssidName`, `radioId`, `site`, `time` and `authType=4`, sending `Csrf-Token` and the session cookie.

The `time` field is sent in **milliseconds** (matching TP-Link's sample code). Some documentation text says microseconds; this is a known documentation ambiguity and must be verified against the customer's controller firmware.

## Customer registration flow

1. `POST /api/v1/omada/integrations` — creates the `omada_integrations` record and an associated `network_devices` router row.
2. `POST /api/v1/omada/integrations/test` — optional pre-save connection test.
3. `POST /api/v1/omada/integrations/{id}/test` — test the saved integration.
4. `GET /api/v1/omada/integrations/{id}/sites` — refresh sites.
5. `PUT /api/v1/omada/integrations/{id}/site` — select a site.
6. `GET /api/v1/omada/integrations/{id}/access-points` — discover APs.
7. `PUT /api/v1/omada/integrations/{id}/access-points` — select APs (`device_ids`).
8. `GET /api/v1/omada/integrations/{id}/ssids` — discover SSIDs.
9. `PUT /api/v1/omada/integrations/{id}/ssid` — select the SSID (`wlan_id`).
10. `POST /api/v1/omada/integrations/{id}/sync` — refresh everything.
11. `GET /api/v1/omada/integrations/{id}/status` — dashboard counters.
12. `GET /api/v1/omada/integrations/{id}/sessions` — client sessions.
13. `POST /api/v1/omada/integrations/{id}/clients/{mac}/disconnect`.
14. `DELETE /api/v1/omada/integrations/{id}` — remove the integration and router.

All tenant routes use the existing `auth:sanctum`, `active.user`, `company.context`, `company.required`, `subscription.active` stack and reuse `routers.*` permissions. Every query includes `company_id`; cross-tenant access returns 404.

## External portal flow

The integration exposes a unique portal key per integration:

```
GET  /api/omada/portal/{portalKey}?clientMac=...&apMac=...&ssidName=...&radioId=...&site=...&t=...
POST /api/omada/portal/{portalKey}/authorize
POST /api/omada/portal/{portalKey}/disconnect
```

`GET /entry` validates the Omada-supplied parameters (MAC format, site membership, selected SSID, timestamp freshness), creates or reuses a pending `omada_client_sessions` row, and returns the portal URL plus the session ID.

Authorization is **never** based on client-supplied values. `POST /authorize` accepts either a `payment_transaction_id` that must belong to the same company and have `status=paid`, or an `access_grant_id` that must be active and unexpired. Only then is the controller called and the local session marked `authorized`.

> **Remaining wiring (requires approval):** the existing `/connect` portal and `PaymentService` still do not call the Omada authorization endpoint. Doing so means changing shared payment/portal code, which was explicitly excluded from this change. Until that hook is added, the frontend/dashboard must call the Omada authorize endpoint after the existing payment flow verifies payment.

## Network connectivity and SSRF

- HTTPS is required by default (`OMADA_ALLOW_INSECURE_HTTP=false`).
- Private, loopback, link-local, reserved addresses and cloud metadata endpoints are rejected.
- Private hosts are allowed only when `OMADA_ALLOW_PRIVATE_HOSTS=true` **and** the integration uses `connection_method=vpn` or `cloud`.
- TLS verification is on by default. Per-integration opt-out is rejected unless `OMADA_ALLOW_SELF_SIGNED=true`.
- Controllers behind NAT should use a public DDNS endpoint, a WireGuard/VPN tunnel, or the cloud controller.

## Credentials and isolation

- `omada_integrations.credentials` is a Laravel `encrypted:array` cast holding `client_id`, `client_secret`, `operator_username`, `operator_password`.
- Credentials are hidden from serialization and never returned; resources only expose `*_set` booleans.
- URLs and tokens are never logged; only integration IDs, company IDs and error codes are logged.
- Composite tenant foreign keys (`(id, company_id)`) protect sites, APs, SSIDs and sessions.

## Configuration

See the `OMADA_*` block in `.env.example` and `config/omada.php`. Platform-wide defaults belong in `.env`; every customer's controller URL, credentials and site selection live encrypted in the database.

## Known limitations

- SSID/portal parsing differs between controller generations. The raw WLAN/SSID records are stored in `metadata` so a version-specific adapter can be added without a schema change.
- Omada Open API availability depends on firmware. OC200 units on older firmware may not expose Open API; update the controller or use OC300/software controller.
- Cloud controller external-portal support must be verified per deployment; if the controller version does not support external portal authorization, authorization will fail with the controller's error rather than a false success.
- Data quotas are not supported by the Omada authorization API; only time-based duration is enforced.
- `OmadaClientSession` expiry/disconnect runs every five minutes via `omada:sync`; a controller outage keeps the local session record and retries disconnection on the next run.

## Tests

`tests/Feature/OmadaIntegrationTest.php` covers registration, credential encryption, connection testing, discovery, tenant isolation, portal parameter validation, paid-payment/grant authorization, and session expiry with mocked Omada responses.
