# Road to Internet — WaiFai Captive Portal / WiFiDog End‑to‑End Guide

This document describes **everything that must happen** for a hotspot customer to go from
"connects to Wi‑Fi" to "has internet", including every HTTP request, who sends it, who
answers it, and what the backend and frontend must do at each step.

Base URLs used below (replace with your deployment):

| Placeholder | Value |
|---|---|
| `{API}` | `https://waifai.shereheyangu.com` (Laravel backend) |
| `{PORTAL}` | `https://waifai.shereheyangu.com` (captive portal, rendered by the **same** backend) |
| `{CONNECT}` | `{API}/connect` (backend-hosted connect page) |

> **Backend-hosted portal:** the captive portal is now rendered by the API app itself
> (`GET {CONNECT}` → `App\Http\Controllers\Web\CaptivePortalController`), not a separate
> frontend SPA. The device is redirected to
> `{CONNECT}?subdomain={slug}&router={router}&session={captive-token}`. Packages are scoped to
> the router's station when `station_plans` rows exist for it, otherwise all active company
> plans are shown. The page is a **single-page UI** with four JavaScript-switched
> states (`initial` / `subscribe` / `voucher` / `redeem`). It calls the existing
> JSON API directly — no separate portal pages/routes:
>
> | Action | Endpoint used by the page |
> |---|---|
> | Load page | `GET {CONNECT}` (renders all states + embedded packages) |
> | Subscribe / pay | `POST {API}/api/v1/portal/{subdomain}/payments`, then poll `GET …/payments/{id}` |
> | Voucher | `POST {API}/api/v1/portal/{subdomain}/vouchers/redeem` |
> | Redeem / continue | `POST {API}/api/v1/portal/{subdomain}/restore`, then `POST {API}/api/v1/captive/sessions/{token}/authorize` |
> | Finish | navigate the browser to `gateway_auth_url` from the API response |
>
> The rest of this document still describes the underlying API/protocol the portal uses.

---

## 1. Actors and channels

| Actor | Role |
|---|---|
| **Device** | Customer phone / laptop browser |
| **AP / Gateway (Ruijie)** | Runs the WiFiDog client (NAS). Intercepts HTTP, redirects to the portal, and opens/closes the client's internet (`fw_allow`/`fw_deny`) |
| **API backend** | Laravel app `{API}`. Owns captive sessions, payments, grants, sessions, and the WiFiDog protocol endpoints |
| **Captive portal** | Backend-rendered single page `{CONNECT}`. Branding, package selection, payment/voucher UX, and the final hand‑off to the AP auth URL (via the API) |
| **Payment provider** | PalmPesa / PalmPay / Flutterwave etc. (USSD push + webhook) |
| **Ruijie Cloud** | Management plane for the AP. Not on the auth data path, but it holds the Captive Portal / External WiFiDog configuration |

Channels (who talks to whom):

```
                         (1) Wi-Fi + HTTP intercepted
   ┌──────────┐  ◄────────────────────────────────────┐
   │  Device  │                                        │
   └────┬─────┘                                        │
        │ (2) browser HTTPS over internet              │
        ├──────────────► {PORTAL}/connect  (frontend)  │
        │                       │                       │
        │                       │ (3) HTTPS JSON         │
        │                       ▼                       │
        │                {API}/api/v1/...               │
        │                                              │
        │ (5) GET http://{gw_address}:{gw_port}/wifidog/auth?token=... │
        └──────────────────────► AP / Gateway ─────────┘
                                     │
                                     │ (6) server-to-server (over internet)
                                     ▼
                              {API}/api/wifidog/auth   +   /api/wifidog/ping
```

Key point: the **browser** talks to `{PORTAL}`/`{API}` over the public internet, while the
**AP auth URL** uses a **private LAN address** (`gw_address:gw_port`) that only the device can
reach. The AP's own validation (`/auth`, `/ping`) is **server‑to‑server over the internet**.

---

## 2. Glossary — what the WiFiDog parameters actually mean

These come in on `GET {API}/api/wifidog/login` from the AP (via the device browser redirect).

| Parameter | Meaning | Example | Notes |
|---|---|---|---|
| `gw_id` | **WiFiDog Gateway ID** — identifies the AP/gateway | `58b4bb192d35` | Ruijie defaults it to the AP MAC (no separators) or the SN. Backend maps it to `network_devices.gateway_id`. Determines tenant (company), router record, and validates that a session belongs to that gateway. Match is case‑insensitive. |
| `gw_sn` | **AP serial number** (Ruijie‑specific) | `G1UQ5C8006474` | Not used for routing; useful for log correlation. |
| `gw_address` (a.k.a. **gw_ip**) | **LAN IP of the AP/gateway** that serves `/wifidog/auth` | `192.168.1.22` | This is what the backend puts into `http://{gw_address}:{gw_port}/wifidog/auth?token=…`. It is a **private** address (reachable only inside the hotspot). It is **not** the backend's IP. When absent, backend falls back to `network_devices.lan_ip`. |
| `gw_port` | **TCP port of the AP's WiFiDog HTTP server** | `2060` | Used with `gw_address` to build the auth URL. Default `2060`. |
| `nasip` | NAS (gateway) IP | `192.168.1.22` | Usually equals `gw_address`. |
| `ip` | **Client device IP** as seen by the AP | `192.168.1.23` | Stored on the captive session, used for matching. |
| `mac` | **Client device MAC** as seen by the AP | `ae:ad:e9:d2:34:28` | Normalized to `AE:AD:E9:D2:34:28`. Used to match the session and to authorize the right client. Android/iOS may randomize it. |
| `ssid` | SSID the client joined | `Tsh 500Masaa24Gift` | Optional context. |
| `ustate` | AP's view of client auth state | `0` | `0` = not authenticated. The AP keeps redirecting while it is `0`. |
| `mac_req` | Ruijie flag | `0` | Informational. |
| `url` | **Original URL the client requested** | `http://connectivitycheck.gstatic.com/generate_204` | iOS/Android captive‑probe URL or a real site. Redirected to after successful auth. |
| `token` | **Captive session token** (64 hex) | from `captive_sessions.token` | Sent by the AP on `/auth` and `/portal`. This is how the backend finds the session. |
| `stage` | Auth stage | `login` / `counters` / `logout` | `login` = first validation, `counters` = periodic traffic update, `logout` = disconnect. |

**Golden rule:** `gw_address`/`gw_port` describe **where the AP's auth endpoint is**, and they are
authoritative from the AP. Never overwrite a known `gw_address` with a router record fallback.

---

## 3. Preconditions (must be true before a user can reach internet)

### 3.1 Backend
- Company is `active`, has a `subdomain`, and at least one `active` InternetPlan (package).
- A `NetworkDevice` (router) exists for the AP with:
  - `gateway_id` = the AP's WiFiDog **Gateway ID** (`gw_id`), exact match,
  - `lan_ip` = the AP's **WiFiDog/NAS LAN IP** (`gw_address`),
  - `wifidog_port` = the AP's WiFiDog port (`gw_port`, default `2060`),
  - `status` = `active`.
- A default payment provider is configured for the company/platform (unless only vouchers are used).
- Webhooks are reachable (`{API}/api/v1/webhooks/payments/{provider}`).
- Config: `CAPTIVE_PORTAL_URL={CONNECT}`, `CAPTIVE_PORTAL_SUCCESS_URL` (final fallback),
  `CAPTIVE_SESSION_TTL`, `APP_URL={API}`.
- `php artisan optimize:clear` after any deploy/config change.

### 3.2 AP / Ruijie Cloud
- SSID `Tsh 500Masaa24Gift` has a Captive Portal policy:
  - **Authentication = External** (WiFiDog), and the **Authentication Device = AP** (or Router if the NAS is the gateway),
  - **Redirection URL = `{API}/api/wifidog/login`** (this is what the *browser* hits),
  - **Auth server = `{API}` host with path `/api/wifidog/`** (this is what the *AP* calls server‑to‑server),
  - **Gateway ID** = the same value as `network_devices.gateway_id`,
  - SSL/port must match the backend (use `https`/`443` if the API is HTTPS‑only).
- The AP must be able to reach `{API}` server‑to‑server. Proof: `wifidog.ping` logs appear every check interval.
- The auth/portal paths must **not** be `/public/api/...` and must **not** contain `login` in the base path.

### 3.3 Frontend
- `{CONNECT}` page is deployed and reads `subdomain` and `session` from the query string.
- It can reach `{API}` and knows the poll interval / statuses.

---

## 4. The happy path, step by step

### Phase 1 — Device associates and tries to browse
1. Device joins the SSID and requests e.g. `http://connectivitycheck.gstatic.com/generate_204` (Android)
   or `http://captive.apple.com/hotspot-detect.html` (iOS).
2. The AP sees an unknown client (`ustate=0`) and redirects the browser to the **Redirection URL**.

**Channel:** Device → AP (HTTP) → AP intercepts.
**AP does:** HTTP/JS redirect to `{API}/api/wifidog/login?gw_id=…&gw_address=…&gw_port=2060&ip=…&mac=…&ssid=…&url=…`.

---

### Phase 2 — WiFiDog login (AP‑supplied redirect → API)

**Channel:** Device browser → API.

```
GET {API}/api/wifidog/login?gw_id=58b4bb192d35&gw_sn=G1UQ5C8006474&gw_address=192.168.1.22
    &gw_port=2060&ip=192.168.1.23&mac=ae:ad:e9:d2:34:28&ssid=Tsh%20500Masaa24Gift
    &ustate=0&mac_req=0&url=http%3A%2F%2Fconnectivitycheck.gstatic.com%2Fgenerate_204
```

**Backend (`WiFiDogService::login`) does:**
1. Resolve the gateway by `gw_id` (`GatewayResolver::resolveActive`). Unknown/inactive → 404.
2. `CaptiveSessionService::resolveOrCreate($gateway, $client)`:
   - normalize MAC, find a reusable session by MAC (or IP if no MAC) for this gateway;
   - store `client_ip`, `client_mac`, `ssid`, `gw_address`, `gw_port`, `requested_url`;
   - `gw_address` precedence: **request → existing session → `network_devices.lan_ip`**;
   - new sessions get a 64‑hex `token` (`captive_sessions.token`), `status=pending`, `expires_at=now+TTL`.
3. If the session is already **authenticated** → 302 to the AP auth URL.
   Else → 302 to the frontend connect page.

**Responses:**

Anonymous/new client:
```
HTTP/1.1 302 Found
Location: {CONNECT}?subdomain=spider&session=<64-hex-token>
```

Already authenticated client (re‑authorize with the AP):
```
HTTP/1.1 302 Found
Location: http://192.168.1.22:2060/wifidog/auth?token=<64-hex-token>
```

**Frontend module:** none yet (browser just follows the redirect to `{CONNECT}`).

---

### Phase 3 — Portal page loads (frontend ↔ API)

**Channel:** Device → Frontend SPA, then Frontend → API.

1. The SPA reads `subdomain` and `session` from the URL.
2. **Recover client context:**
   ```
   GET {API}/api/v1/captive/sessions/{token}
   Authorization: none (public)
   ```
   **Backend (`CaptiveSessionController::show` → `toPublicArray`) returns:**
   ```json
   {
     "status": "pending",
     "expires_at": "2026-09-17T14:42:47+00:00",
     "authenticated_at": null,
     "network": { "name": "SpiderTechStudios hotspot", "subdomain": "spider" },
     "gateway": { "name": "Router1" },
     "client": { "ip": "192.168.1.23", "mac": "AE:AD:E9:D2:34:28", "ssid": "Tsh 500Masaa24Gift" },
     "gateway_auth_url": "http://192.168.1.22:2060/wifidog/auth?token=…"
   }
   ```
   > `gateway_auth_url` is returned for this session's gateway address/port. It is the final
   > hand‑off URL the frontend must open **after** the customer is authorized.

3. **Branding + packages:**
   ```
   GET {API}/api/v1/portal/{subdomain}
   ```
   Returns `company` (name, colors, welcome message, payment method) and `packages` (active plans).

**Frontend responsibilities:** show branding, packages, a pay button, voucher field, and a
"restore access" option; keep the `session` token for all subsequent calls.

---

### Phase 4 — Payment (frontend ↔ API ↔ provider)

**Channel:** Frontend → API → Payment provider → API webhook → API.

1. **Initiate payment** (frontend → API):
   ```
   POST {API}/api/v1/portal/{subdomain}/payments
   Content-Type: application/json

   {
     "internet_plan_id": 1,
     "customer_name": "Jane Guest",
     "customer_phone": "0712345678",
     "payment_method": "mobile_money",
     "captive_session": "<64-hex-token>"
   }
   ```
   **Backend (`PaymentService::createForPortal`) does:**
   - creates `payment_transactions` (`status=pending`, `metadata.captive_session=<token>`),
   - creates a `platform_payments` record and performs the provider USSD push.
   **Response `201`:**
   ```json
   {
     "id": 45, "reference": "PAY-XXXX", "amount": "2500.00", "currency": "TZS",
     "status": "pending", "provider": "palmpesa", "provider_reference": "PALMPESA-…",
     "captive_status": "pending", "gateway_auth_url": null, "next_action": "poll_payment"
   }
   ```

2. **Poll payment status** (frontend → API):
   ```
   GET {API}/api/v1/portal/{subdomain}/payments/{payment}
   ```
   Backend reconciles with the provider. When paid:
   - `PaymentService::markPortalPaid()` runs,
   - creates the `access_grants` row + `network_sessions` row,
   - `authorizeCaptiveIfPresent()` flips the captive session to `status=authenticated`.
   **Response (paid):**
   ```json
   {
     "status": "paid", "captive_status": "authenticated",
     "gateway_auth_url": "http://192.168.1.22:2060/wifidog/auth?token=…",
     "next_action": "open_gateway_auth_url"
   }
   ```
   > `next_action` tells the frontend what to do: `poll_payment`, `open_gateway_auth_url`,
   > `start_session`, `gateway_auth_unavailable`, `retry_payment`.

3. **(Alternative) Start hotspot session explicitly** (frontend → API), if the frontend wants to
   create the session itself:
   ```
   POST {API}/api/v1/portal/{subdomain}/sessions
   { "payment_transaction_id": 45, "captive_session": "<token>", "mac_address": "AE:AD:E9:D2:34:28" }
   ```
   Response includes `captive.gateway_auth_url`.

**Payment provider webhook (provider → API):**
```
POST {API}/api/v1/webhooks/payments/{provider}
```
→ `PlatformPaymentService::markPaid()` → `PaymentService::markPortalPaid()` → grant + session + captive authorize.

**Backend responsibilities:** create pending payment, push USSD, reconcile on poll/webhook, and on
paid create the grant/session and mark the captive session authenticated.

**Frontend responsibilities:** collect phone + package, POST payment, poll until
`status=paid`, then open `gateway_auth_url`.

---

### Phase 5 — Complete WiFiDog auth (device ↔ AP ↔ API)

**Channel:** Device browser → AP, then AP → API (server‑to‑server).

1. **Frontend opens the gateway auth URL:**
   ```
   window.location.href = data.gateway_auth_url;
   // = http://192.168.1.22:2060/wifidog/auth?token=<64-hex-token>
   ```

2. **Device requests the AP's auth endpoint:** `GET http://{gw_address}:{gw_port}/wifidog/auth?token=…`

3. **AP validates with the backend (server‑to‑server over internet):**
   ```
   GET {API}/api/wifidog/auth/?stage=login&ip=192.168.1.23&mac=ae:ad:e9:d2:34:28
       &token=<64-hex-token>&incoming=0&outgoing=0&gw_id=58b4bb192d35
   ```
   **Backend (`WiFiDogService::auth` → `CaptiveSessionService::authorizeToken`) does:**
   - `findByToken($token)` → looks up **`captive_sessions.token`**,
   - rejects if not found / expired / not `authenticated` / MAC mismatch / access grant inactive,
   - **returns plain text `Auth: 1`** on success (or `Auth: 0` on failure).
   ```
   HTTP/1.1 200 OK
   Content-Type: text/plain

   Auth: 1
   ```
   > The gateway parses `Auth: <code>` (`0` denied, `1` allowed, `5` validation). It expects
   > HTTP/1.0‑style plain text; do not wrap it in JSON.

4. **AP acts on the code:**
   - `Auth: 1` → `fw_allow(client, KNOWN)` — **the client is now allowed through the firewall/NAT**,
     then the AP 302‑redirects the browser to the auth server portal script:
     ```
     Location: {API}/api/wifidog/portal/?gw_id=58b4bb192d35&token=<64-hex-token>
     ```
   - `Auth: 0` → the AP denies and redirects the browser to the message URL (e.g. `?message=denied`).

5. **Backend portal script (`/api/wifidog/portal`):**
   - if the session is authenticated → 302 to the **original `url`** (or `CAPTIVE_PORTAL_SUCCESS_URL`):
     ```
     Location: http://connectivitycheck.gstatic.com/generate_204
     ```
   - if not authenticated (or a gateway `message=` is present) → 302 to `{CONNECT}?...` so the
     customer returns to the portal instead of looping.

6. **Internet works.** The OS captive‑portal probe returns success and the device leaves the portal.

7. **Ongoing:** the AP periodically calls:
   - `GET {API}/api/wifidog/ping?gw_id=…&sys_uptime=…&…` → `Pong` (liveness),
   - `GET {API}/api/wifidog/auth/?stage=counters&token=…&incoming=…&outgoing=…` → `Auth: 1` while valid.

---

## 5. Alternative paths

### 5.1 Voucher (no mobile money)
**Frontend → API:**
```
POST {API}/api/v1/portal/{subdomain}/vouchers/redeem
{ "code": "123456", "customer_name": "Jane", "customer_phone": "0712345678",
  "captive_session": "<token>", "mac_address": "AE:AD:E9:D2:34:28" }
```
Backend redeems the voucher, creates the grant + session, authorizes the captive session, and
returns `captive.gateway_auth_url`. The frontend then opens it (Phase 5).

### 5.2 Authorize after payment from the captive API
**Frontend → API:**
```
POST {API}/api/v1/captive/sessions/{token}/authorize
{ "payment_transaction_id": 45 }        // or { "access_grant_id": 8 }
```
Returns the public payload **plus** `gateway_auth_url`. Open it (Phase 5).

### 5.3 Returning customer (restore)
```
POST {API}/api/v1/portal/{subdomain}/restore
{ "customer_phone": "0712345678", "mac_address": "AE:AD:E9:D2:34:28" }
```
Returns the active grant/session. If the captive session is still valid and authenticated, the
gateway auth URL can be opened again.

---

## 6. Backend modules and responsibilities

| Module | File | Responsibility |
|---|---|---|
| WiFiDog endpoints | `app/Http/Controllers/Api/WiFiDog/WiFiDogController.php` | Thin controller for `/login`, `/auth`, `/portal`, `/ping` |
| WiFiDog protocol | `app/Services/WiFiDogService.php` | Login routing, auth decision, portal redirect, ping; end‑to‑end tracing |
| Captive session lifecycle | `app/Services/CaptiveSessionService.php` | `resolveOrCreate`, `authenticate`, `linkHotspotSession`, `authorizeToken`, `gatewayAuthRedirectUrl`, `portalRedirectUrl`, `toPublicArray` |
| Captive API | `app/Http/Controllers/Api/V1/Captive/CaptiveSessionController.php` | `GET /captive/sessions/{token}`, `POST /captive/sessions/{token}/authorize` |
| Portal API | `app/Http/Controllers/Api/V1/Portal/PortalController.php` | bootstrap, payments, vouchers, sessions, restore |
| Portal payloads | `app/Services/PortalService.php`, `app/Http/Resources/PortalPaymentResource.php` | Branding/packages, payment `next_action` + `gateway_auth_url` |
| Payments | `app/Services/PaymentService.php`, `app/Services/PlatformPaymentService.php` | Create/reconcile payments; on paid → grant + session + captive authorize |
| Grants/sessions | `app/Services/NetworkSessionService.php` | Create `access_grants` + `network_sessions` from a paid payment or voucher |
| Gateway resolution | `app/Services/GatewayResolver.php` | Map `gw_id` → active `NetworkDevice` |
| Routes | `routes/Api/wifidog.php`, `routes/Api/v1/captive.php`, `routes/Api/v1/portal.php` | Endpoint definitions |
| Logging | `config/logging.php` (`wifidog` channel), `app/Http/Middleware/LogWiFiDogRequests.php` | `storage/logs/wifidog.log`, `wifidog.hit` for every WiFiDog request |

Backend must:
- Always return `Auth: 1`/`Auth: 0` as **plain text** on `/auth`.
- Keep `captive_sessions.token` as the single source of truth for the token.
- Never overwrite the AP‑reported `gw_address`/`gw_port`.
- Send a denied client back to `{CONNECT}` (never to the original URL) to avoid loops.
- Log every step so a missing AP→API leg is obvious.

---

## 7. Frontend modules and responsibilities

| Module | Responsibility |
|---|---|
| Connect page (`/connect`) | Read `subdomain` + `session`; call `GET /api/v1/captive/sessions/{token}`; render status |
| Branding/packages | `GET /api/v1/portal/{subdomain}` |
| Payment flow | `POST /api/v1/portal/{subdomain}/payments`, then poll `GET …/payments/{id}` |
| Voucher flow | `POST /api/v1/portal/{subdomain}/vouchers/redeem` |
| Restore flow | `POST /api/v1/portal/{subdomain}/restore` |
| Final hand‑off | When paid/authorized, set `window.location.href = gateway_auth_url` |
| Error handling | On `message=denied` or `gateway_auth_unavailable`, return to `/connect` and show retry |

Frontend rules:
- Always pass `captive_session` (the 64‑hex token) with payment/voucher calls.
- Only open `gateway_auth_url` **after** the customer is authorized.
- `gateway_auth_url` is an `http://{gw_address}:{gw_port}` LAN URL; opening it from the client is
  what makes the AP grant internet. Do not fetch it with `fetch()`/`XHR` — navigate the browser.
- Handle `next_action` from the payment resource.

---

## 8. Failure map and diagnostics

All WiFiDog events go to `storage/logs/wifidog.log`.

```
tail -f storage/logs/wifidog.log
grep 'mac:AEADE9D23428' storage/logs/wifidog.log      # trace one client
```

| Symptom | Look for | Cause / fix |
|---|---|---|
| Phone keeps returning to the portal | `portal.out reason=gateway_message gateway_message=denied`; **no** `auth.in` | The AP never validated with us. Fix the AP's **Auth server / Portal Server IP** (public host, correct scheme/port/path). |
| No `wifidog.ping.in` from the AP | `wifidog.hit` has no `direction:"ap->api"` | AP cannot reach the API server‑to‑server (DNS/TLS/port/allowlist). |
| `auth.in` present but `auth.out auth_code:0` | `auth.token_lookup` → `session_found`, `mac_matches` | Denied: wrong token, MAC mismatch, expired session, or inactive access grant. |
| Redirect loop / `ERR_TOO_MANY_REDIRECTS` | `login.out direction=api->ap` repeating | Denied client must go to `{CONNECT}` (not the original URL); ensure the AP is actually authorizing (`auth.in` appears). |
| Wrong gateway host in auth URL | `gw_address_source` in `captive_session_reused` | `gw_address` must be the AP‑reported value, not a router `lan_ip` fallback. |
| `unhandled_route` | `wifidog.unhandled_route` | The AP called a path we don't serve — fix its AuthServer path to `/api/wifidog/`. |
| Payment paid but captive not authenticated | `wifidog.captive_session_authenticated` missing | Check `authorizeCaptiveIfPresent` / webhook delivery. |

Expected successful sequence:

```
login.in → login.gateway → login.session → login.out (api->ap)
auth.in → auth.token_lookup (session_found=true, mac_matches=true) → auth.out auth_code:1
login.out/portal.out (api->mobile) → device leaves portal
ping.in/out (ap->api) periodically
```

---

## 9. Minimal configuration reference

| Env / setting | Purpose |
|---|---|
| `APP_URL={API}` | Backend base URL |
| `CAPTIVE_PORTAL_URL={CONNECT}` | Where the browser portal lives |
| `CAPTIVE_PORTAL_SUCCESS_URL` | Final redirect when no original URL is known |
| `CAPTIVE_SESSION_TTL` | Pending captive session lifetime (minutes) |
| `FRONTEND_URL` | Portal origin fallback |
| AP: Redirection URL | `{API}/api/wifidog/login` (browser) |
| AP: Auth server host + path | `{API}` + `/api/wifidog/` (server‑to‑server) |
| AP: Gateway ID | Must equal `network_devices.gateway_id` |

After any deploy or config change:

```
php artisan optimize:clear
```

---

## 10. One‑line summary of the contract

> The AP redirects the **browser** to `{API}/api/wifidog/login`; the backend creates a
> `captive_sessions` row with a 64‑hex `token` and sends the browser to `{CONNECT}`. The customer
> pays (or redeems a voucher); the backend marks the captive session `authenticated` and hands the
> frontend `gateway_auth_url = http://{gw_address}:{gw_port}/wifidog/auth?token=…`. The frontend
> **navigates** the browser there; the AP calls `{API}/api/wifidog/auth` server‑to‑server, receives
> **`Auth: 1`**, opens the firewall (`fw_allow`), and redirects the browser to
> `/api/wifidog/portal` → the original URL. Internet.
