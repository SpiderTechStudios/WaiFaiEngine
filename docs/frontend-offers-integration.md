# Frontend guide: Free WiFi Offers

Use this to integrate **owner-configured free WiFi offers** in the operator dashboard and (if needed) a custom captive portal.

**Base URL:** `https://api.waifai.co.tz/api/v1`  
**Auth (operator):** Sanctum bearer token + company context (same as other operations APIs)  
**Auth (portal claim):** none (public)

---

## What an offer is

An offer is a **time-limited free-access promotion**. When a customer claims it:

- They get an `access_grant` with `source: "offer"`
- Duration starts **now** (offer duration, or from a linked package)
- **No payment / no revenue** is recorded
- Limit: **one claim per phone + device MAC** per offer
- Optional: total claim cap (`max_claims`)

The backend captive page (`/connect`) already shows and claims offers. The operator **dashboard** still needs UI to create/manage them.

---

## Two integration surfaces

| Surface | Who | Purpose |
|---------|-----|---------|
| **Operator dashboard** | Logged-in owner/staff | CRUD + activate/deactivate offers |
| **Captive / portal** | WiFi customer | See active offer + claim free access |

---

## 1. Operator dashboard endpoints (Sanctum)

All require:

```http
Authorization: Bearer {token}
Accept: application/json
```

Plus the usual company context used by other `/api/v1/*` operations routes.

### Permissions

| Permission | Used by |
|------------|---------|
| `offers.view` | List, show |
| `offers.create` | Create |
| `offers.update` | Update, activate, deactivate |
| `offers.delete` | Delete |

### Endpoints

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/offers` | `offers.view` |
| `POST` | `/offers` | `offers.create` |
| `GET` | `/offers/{id}` | `offers.view` |
| `PATCH` | `/offers/{id}` | `offers.update` |
| `DELETE` | `/offers/{id}` | `offers.delete` |
| `POST` | `/offers/{id}/activate` | `offers.update` |
| `POST` | `/offers/{id}/deactivate` | `offers.update` |

### List offers

```http
GET /api/v1/offers?is_active=true&per_page=15
```

Response shape:

```json
{
  "status": true,
  "code": 200,
  "message": "Offers retrieved",
  "data": {
    "items": [ /* Offer */ ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
  }
}
```

### Create offer

```http
POST /api/v1/offers
Content-Type: application/json
```

**Option A — standalone duration** (most common):

```json
{
  "title": "3 Hours Free",
  "description": "Enjoy 3 hours on us",
  "duration": 3,
  "duration_unit": "HOURS",
  "max_claims": 5,
  "is_active": true,
  "starts_at": "2026-09-24T00:00:00+03:00",
  "ends_at": "2026-09-30T23:59:59+03:00",
  "router_ids": []
}
```

**Option B — link an existing package:**

```json
{
  "title": "Free Daily Pass",
  "internet_plan_id": 12,
  "max_claims": 100,
  "is_active": true,
  "router_ids": [3]
}
```

Rules:

- `title` required
- On create: either (`duration` + `duration_unit`) **or** `internet_plan_id`
- `duration_unit`: `HOURS` | `DAYS` | `WEEKS` | `MONTHS`
- `router_ids` empty / omitted = **company-wide**
- Non-empty `router_ids` = only those routers
- `starts_at` / `ends_at` optional window; omit = always in window while active
- `max_claims` null/omit = unlimited claims (still one per phone+MAC)
- `is_active` defaults to true if omitted

Success: **201** + `Offer` in `data`.

### Offer object (`data`)

```ts
type Offer = {
  id: number;
  title: string;
  description: string | null;
  starts_at: string | null;      // ISO datetime
  ends_at: string | null;
  duration: number | null;
  duration_unit: 'HOURS' | 'DAYS' | 'WEEKS' | 'MONTHS' | null;
  max_claims: number | null;
  claims_count: number;
  remaining_claims: number | null; // null = unlimited
  is_active: boolean;
  internet_plan_id: number | null;
  package?: {
    id: number;
    name: string;
    duration: number;
    duration_unit: string;
    price: string | number;
  } | null;
  routers?: Array<{
    id: number;
    name: string;
    gateway_type: string;
  }>; // empty = company-wide
  created_at: string | null;
};
```

### Update / activate / deactivate / delete

```http
PATCH /api/v1/offers/{id}
POST  /api/v1/offers/{id}/activate
POST  /api/v1/offers/{id}/deactivate
DELETE /api/v1/offers/{id}
```

Notes:

- `DELETE` removes the offer, or **deactivates** it if it already has claims (message still success).
- Prefer activate/deactivate toggles in the UI for campaigns.

---

## 2. Suggested operator UI

### Offers list page

Columns: title, window (`starts_at`–`ends_at`), duration, claims (`claims_count` / `max_claims` or “∞”), routers (or “All”), active badge, actions.

Filters: Active / Inactive (`?is_active=`).

### Create / edit form

| Field | UI |
|-------|----|
| Title | required text |
| Description | optional textarea |
| Duration OR Package | radio: “Custom duration” vs “Use package” |
| Duration + unit | number + select (Hours/Days/Weeks/Months) |
| Package | select from existing internet plans |
| Max claims | optional number |
| Start / End | datetime pickers (optional) |
| Routers | multi-select; empty = all routers |
| Active | toggle |

### Validation errors (422)

Show field messages from `data` (Laravel validation map), e.g.:

- `duration`: Provide a duration or select a package…
- `ends_at`: must be after start

---

## 3. Portal / captive claim (public)

### Claim endpoint

```http
POST /api/v1/portal/{subdomain}/offers/claim
Content-Type: application/json
Accept: application/json
```

```json
{
  "offer_id": 1,
  "customer_phone": "0711987654",
  "customer_name": "Mteja",
  "captive_session": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "mac_address": "AA:BB:CC:DD:EE:FF"
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `offer_id` | yes | Active claimable offer id |
| `customer_phone` | yes | TZ mobile (`07…` / `255…`) |
| `customer_name` | no | |
| `captive_session` | no* | 32-char session token from WiFiDog `/connect?session=` |
| `mac_address` | no | Taken from captive session when omitted |

\*For real hotspot unlock, always send `captive_session` so the API can authorize the device and return `gateway_auth_url`.

### Success (201)

```json
{
  "status": true,
  "code": 201,
  "message": "Offer claimed",
  "data": {
    "customer": { "id": 1, "name": "Mteja", "phone": "0711987654" },
    "access_grant": {
      "id": 10,
      "status": "active",
      "source": "offer",
      "starts_at": "2026-09-24T17:00:00+00:00",
      "expires_at": "2026-09-24T20:00:00+00:00"
    },
    "package": { "id": 5, "name": "…", "duration": 3, "duration_unit": "HOURS" },
    "offer": {
      "id": 1,
      "title": "3 Hours Free",
      "duration": 3,
      "duration_unit": "HOURS",
      "remaining_claims": 4
    },
    "captive": {
      "gateway_auth_url": "http://192.168.0.144:2060/wifidog/auth?…"
    }
  }
}
```

Frontend action on success:

1. If `data.captive.gateway_auth_url` exists → **redirect** there immediately  
2. Else show “Offer claimed — reconnect to WiFi”

### Common claim errors (422)

| Message / field | Meaning | UI |
|-----------------|---------|-----|
| Offer not active | Deactivated | Hide claim |
| Not available right now | Outside `starts_at`/`ends_at` | Hide / show “not yet” |
| Fully claimed | `max_claims` reached | Hide claim |
| Already claimed | Same phone+MAC | “You already used this offer” |

---

## 4. How the backend `/connect` portal already does it

The server-rendered captive page embeds:

```js
PORTAL.offer = {
  id, title, description, duration, duration_unit,
  remaining_claims, claimed
} | null
```

Then claims via:

`POST /api/v1/portal/{subdomain}/offers/claim`

with `offer_id`, phone, `captive_session`, `mac_address`.

**If the product uses backend `/connect` only**, dashboard CRUD is the main frontend work.  
**If you also have a custom frontend portal**, mirror that claim flow and read offer from your bootstrap (or pass `offer` from your own API).

---

## 5. Recommended dashboard flows

### Create campaign

1. Owner opens **Offers** → **New offer**
2. Fill title + duration (or package) + optional window / routers / max claims
3. Save → show in list as Active
4. Customers on matching captive portal see the offer and can claim

### Pause campaign

`POST /offers/{id}/deactivate` — offer disappears from captive portal.

### Resume

`POST /offers/{id}/activate` — returns if still within window and has remaining claims.

---

## 6. Minimal TypeScript helpers (dashboard)

```ts
const API = 'https://api.waifai.co.tz/api/v1';

async function listOffers(token: string, params?: { is_active?: boolean }) {
  const q = new URLSearchParams();
  if (params?.is_active != null) q.set('is_active', String(params.is_active));
  const res = await fetch(`${API}/offers?${q}`, {
    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
  });
  return res.json();
}

async function createOffer(token: string, body: Record<string, unknown>) {
  const res = await fetch(`${API}/offers`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify(body),
  });
  return res.json();
}

async function claimOffer(subdomain: string, body: {
  offer_id: number;
  customer_phone: string;
  customer_name?: string;
  captive_session?: string;
  mac_address?: string;
}) {
  const res = await fetch(`${API}/portal/${encodeURIComponent(subdomain)}/offers/claim`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return { ok: res.ok, status: res.status, json: await res.json() };
}
```

---

## 7. QA checklist

**Dashboard**

- [ ] Create offer with duration → appears in list  
- [ ] Create offer with `internet_plan_id`  
- [ ] Scope to one router → other routers do not see it on captive  
- [ ] Deactivate → captive no longer shows offer  
- [ ] Activate again within window → captive shows it  
- [ ] `max_claims` reached → claim returns 422  

**Captive / claim**

- [ ] Claim with phone + captive session → redirect to gateway auth  
- [ ] Same phone+MAC claim twice → already claimed  
- [ ] Different phone can still claim (until max)  
- [ ] No payment / revenue created for offer claims  

---

## 8. OpenAPI

After deploy: `https://api.waifai.co.tz/api/documentation`

Operations:

- `listOffers`, `createOffer`, `showOffer`, `updateOffer`, `deleteOffer`
- `activateOffer`, `deactivateOffer`
- `portalClaimOffer`

---

## Summary for frontend

| Need | Endpoint |
|------|----------|
| Manage offers (dashboard) | `GET/POST/PATCH/DELETE /offers` + activate/deactivate |
| Customer claims free WiFi | `POST /portal/{subdomain}/offers/claim` |
| Backend captive already | Uses embedded `PORTAL.offer` — no frontend change required for `/connect` |
