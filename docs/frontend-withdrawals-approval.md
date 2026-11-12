# Frontend guide: Withdrawals with admin approval

This is the end-to-end withdrawal journey for the WaiFai dashboard, including the
OTP confirmation and the new **platform-admin approval** step.

**Base URL:** `https://api.waifai.co.tz/api/v1`  
**Auth:** Sanctum bearer token + company context  
**Currency:** TZS (server-side default)

> Backend prerequisite: run `2026_11_10_100000_add_approval_fields_to_withdrawals_table.php`.
> Without it, `pending_approval`, `approved_at`, `approved_by`, `denied_at`,
> `denied_by` and `denial_reason` do not exist.

---

## 1. Who does what

| Actor | Screens | Can do |
| --- | --- | --- |
| Tenant owner/operator with `withdrawals.view` / `withdrawals.create` | Client dashboard → Withdrawals | Manage payout accounts, preview fees, request, OTP-confirm, cancel, view history |
| Platform admin (`platform.admin`) or superadmin | Admin dashboard → Withdrawals | List all client withdrawals, inspect, approve (starts provider payout), approve manually (cash/offline, no provider call), deny (refunds) |

Tenant routes live under `/api/v1/withdrawals`. Admin routes live under
`/api/v1/admin/withdrawals` and are **not** company-scoped — do not show them in
the client dashboard.

---

## 2. Status lifecycle

```
pending ──confirm OTP──> pending_approval ──admin approve──> processing ──provider──> completed
   │                          │                                                  └──> failed
   │                          ├──admin approve-manual (cash/offline)──> completed
   │                          └──admin deny──> denied
   └──client cancel──> cancelled
```

| Status | Meaning | Client action | Admin action |
| --- | --- | --- | --- |
| `pending` | Created, wallet debited as a hold, OTP sent | Enter OTP, resend OTP, cancel | None |
| `pending_approval` | OTP confirmed; waiting for admin | Wait | Approve or deny |
| `processing` | Approved; payout sent to the provider | Wait | None (provider webhook completes it) |
| `completed` | Paid to the client (provider payout or manual cash/offline approval) | Done | None |
| `failed` | Payout failed; wallet refunded | Can request a new withdrawal | None |
| `denied` | Admin rejected; wallet refunded | See `denial_reason`, request again | None |
| `cancelled` | Client cancelled before confirm; wallet refunded | — | None |
| `expired` | Reserved for expiry handling | — | — |

The wallet is debited **when the request is created** (`pending`). The full gross
amount is credited back on `cancelled`, `denied` or `failed`.

---

## 3. Response envelope

Every endpoint returns:

```json
{ "status": true, "code": 200, "message": "Success", "data": {} }
```

Errors use `status: false` and the same `code`/`message` fields:

| Code | Meaning |
| --- | --- |
| 401 | Not authenticated |
| 403 | Missing permission or not a platform admin |
| 404 | Withdrawal not found / belongs to another company |
| 422 | Validation error; render `data` field messages |
| 429 | Rate limited |

---

## 4. Payout accounts (client)

A payout account is the mobile-money destination. Network is auto-detected from
the phone number (e.g. M-Pesa, Airtel Money); do not ask the user to choose a
network manually.

### List

```http
GET /api/v1/withdrawals/payout-accounts
```

`data` is a flat array (not paginated):

```json
[
  {
    "id": 3,
    "name": "Asha Mohamed",
    "phone": "255754000001",
    "network": "mpesa",
    "payment_method_id": 2,
    "payment_method_slug": "mpesa",
    "payment_method_name": "M-Pesa",
    "payment_method_logo_url": "https://...",
    "is_default": true,
    "created_at": "2026-11-11T09:00:00+00:00",
    "updated_at": "2026-11-11T09:00:00+00:00"
  }
]
```

### Create

```http
POST /api/v1/withdrawals/payout-accounts
Content-Type: application/json

{ "name": "Asha Mohamed", "phone": "0754000001", "is_default": true }
```

- `name` required, max 255
- `phone` required, max 50
- `is_default` optional boolean (the first account becomes default automatically)

Returns `201` with a single payout account object.

### Update / delete / set default

```http
PATCH  /api/v1/withdrawals/payout-accounts/{id}
DELETE /api/v1/withdrawals/payout-accounts/{id}
POST   /api/v1/withdrawals/payout-accounts/{id}/default
```

Update uses the same `name` + `phone` payload. `setDefault` returns the updated
account.

---

## 5. Preview fees (client)

Always preview before requesting; never calculate fees on the client.

```http
POST /api/v1/withdrawals/preview
Content-Type: application/json

{
  "amount": 10000,
  "payout_account_id": 3,
  "payment_provider_slug": "afriqorepay"
}
```

- `amount` required, numeric, min 1
- `payout_account_id` optional (or send `destination_phone`)
- `payment_provider_slug` optional; omit to use the platform default payout provider
- `destination_phone`, `phone`, `provider`, `currency` optional

Response `data`:

```json
{
  "amount": 10000,
  "currency": "TZS",
  "platform_fee": 201,
  "provider_fee": 100.5,
  "fee_total": 301.5,
  "net_amount": 9698.5,
  "platform_percent": 2,
  "platform_fixed": 1,
  "provider_percent": 1,
  "provider_fixed": 0.5,
  "network": "mpesa",
  "payment_provider": { "slug": "afriqorepay", "name": "AfriqorePay" },
  "wallet_balance": 50000,
  "sufficient_balance": true,
  "payout_account": {
    "id": 3,
    "name": "Asha Mohamed",
    "phone": "255754000001",
    "network": "mpesa"
  }
}
```

Show `amount` (gross), `fee_total`, `net_amount` and `wallet_balance`. The client
receives `net_amount`; the wallet is debited by the gross `amount`.

Validation errors (422):
- amount greater than zero
- "The withdrawal amount is too small after platform and provider fees."
- invalid `payment_provider_slug`
- insufficient balance is returned on **request**, not preview

---

## 6. Request a withdrawal (client)

```http
POST /api/v1/withdrawals
Content-Type: application/json

{
  "amount": 10000,
  "payout_account_id": 3,
  "payment_provider_slug": "afriqorepay"
}
```

Alternative without a saved account:

```json
{
  "amount": 10000,
  "destination_phone": "0754000001",
  "destination_name": "Asha Mohamed"
}
```

Response `201`:

```json
{
  "id": 42,
  "reference": "WD-AB12CD34EF",
  "status": "pending",
  "requires_confirmation": true,
  "requires_approval": false,
  "amount": "10000.00",
  "platform_fee": "201.00",
  "provider_fee": "100.50",
  "fee_total": "301.50",
  "net_amount": "9698.50",
  "currency": "TZS",
  "destination_phone": "255754000001",
  "destination_name": "Asha Mohamed",
  "network": "mpesa",
  "payment_provider_slug": "afriqorepay",
  "otp_channel": "email",
  "otp_expires_at": "2026-11-11T09:10:00+00:00",
  "requested_at": "2026-11-11T09:00:00+00:00"
}
```

`message` is: "Withdrawal requested. Enter the confirmation code sent to you."

Then open the OTP screen using `otp_channel` and `otp_expires_at`.

Errors:
- `422 amount: ["Insufficient wallet balance."]`
- `422 destination_phone: ["A payout mobile number is required."]`
- `422 payment_provider_slug: ["This payment provider does not support payouts."]`

---

## 7. Confirm OTP (client)

```http
POST /api/v1/withdrawals/{id}/confirm
Content-Type: application/json

{ "otp": "123456" }
```

`otp` must be exactly 6 digits.

Success moves the withdrawal to **`pending_approval`**:

```json
{
  "id": 42,
  "status": "pending_approval",
  "requires_confirmation": false,
  "requires_approval": true,
  "confirmed_at": "2026-11-11T09:01:00+00:00"
}
```

> The backend response message still says "confirmed and payout started". That is
> stale copy — payout starts only after admin approval. In the UI say:
> **"Confirmed. Waiting for WaiFai approval."**

Errors (422, under `data`):

| Field | Message |
| --- | --- |
| `withdrawal` | `This withdrawal is already being processed.` |
| `otp` | `No confirmation code is available. Request a new one.` |
| `otp` | `This confirmation code has expired. Request a new one.` |
| `otp` | `Too many incorrect attempts. Request a new confirmation code.` |
| `otp` | `The confirmation code is incorrect.` |

After 5 incorrect attempts (configurable) the user must resend.

### Resend OTP

```http
POST /api/v1/withdrawals/{id}/resend-otp
```

- Allowed only while `status = pending` and not yet confirmed
- Cooldown default 60 seconds (configurable)
- Returns the withdrawal resource; use `otp_expires_at` to restart the countdown
- Error: `otp: ["Please wait before requesting another confirmation code."]`

### Cancel

```http
DELETE /api/v1/withdrawals/{id}
```

- Only allowed while `status = pending` (before OTP confirmation)
- Wallet is refunded
- Not allowed for `pending_approval` or later

---

## 8. Client withdrawal list, detail and stats

### List

```http
GET /api/v1/withdrawals?per_page=15
```

Response `data`:

```json
{
  "wallet_balance": 40000,
  "currency": "TZS",
  "items": [ /* WithdrawalResource[] */ ],
  "meta": { "current_page": 1, "last_page": 3, "total": 42 }
}
```

### Detail

```http
GET /api/v1/withdrawals/{id}
```

Returns one `WithdrawalResource` (fields below).

### Stats

```http
GET /api/v1/withdrawals/stats
```

```json
{
  "currency": "TZS",
  "wallet_balance": 40000,
  "pending_count": 1,
  "pending_amount": 10000,
  "pending_approval_count": 2,
  "pending_approval_amount": 25000,
  "processing_count": 0,
  "processing_amount": 0,
  "completed_count": 10,
  "completed_amount": 150000,
  "failed_count": 1,
  "failed_amount": 5000,
  "denied_count": 1,
  "denied_amount": 8000,
  "cancelled_count": 0,
  "cancelled_amount": 0,
  "platform_fee_total": 1200,
  "provider_fee_total": 600,
  "fee_total": 1800,
  "net_paid_total": 148200,
  "total_count": 15,
  "total_amount": 198000
}
```

Use `pending_approval_count` for a client-side "Waiting for approval" badge.

---

## 9. Withdrawal object

```ts
interface Withdrawal {
  id: number;
  reference: string;
  status:
    | 'pending'
    | 'pending_approval'
    | 'processing'
    | 'completed'
    | 'failed'
    | 'denied'
    | 'cancelled'
    | 'expired';
  requires_confirmation: boolean;
  requires_approval: boolean;

  provider: string | null;
  network: string | null;
  payment_method_id: number | null;
  payment_method_slug: string | null;
  payment_method_name: string | null;
  payment_method_logo_url: string | null;
  payment_provider_slug: string | null;
  payment_provider: { id: number; name: string | null; slug: string } | null;
  payout_account: PayoutAccount | null;

  destination_phone: string | null;
  destination_name: string | null;

  amount: string;          // decimal string, e.g. "10000.00"
  platform_fee: string;
  provider_fee: string;
  fee_total: string;
  net_amount: string;
  currency: string;
  fee_breakdown: {
    platform: { percent: number; fixed: number; amount: number };
    provider: { slug: string; name: string; percent: number; fixed: number; amount: number };
    total: number;
    net_amount: number;
    currency: string;
    wallet_balance_before: number;
    wallet_balance_after: number;
  } | null;

  otp_channel: 'email' | 'sms' | null;
  otp_expires_at: string | null;
  confirmed_at: string | null;
  approved_at: string | null;
  approved_by: number | null;
  denied_at: string | null;
  denied_by: number | null;
  denial_reason: string | null;

  requested_at: string | null;
  processed_at: string | null;
  failed_at: string | null;
  cancelled_at: string | null;
  failure_reason: string | null;
  company?: Company;       // admin responses only
  created_at: string;
}
```

Money is returned as decimal strings. Format with `Number(...)` only for display.

---

## 10. Admin queue (platform admin / superadmin)

All routes require `platform.admin` (or `superadmin`). They are cross-tenant.

### List

```http
GET /api/v1/admin/withdrawals?status=pending_approval&company_id=12&per_page=15
```

- `status` optional filter
- `company_id` optional filter
- `per_page` optional, default 15

Response `data`:

```json
{
  "items": [ /* WithdrawalResource with company loaded */ ],
  "meta": { "current_page": 1, "last_page": 2, "total": 18 }
}
```

Recommended default filter for the approval screen: `status=pending_approval`.

### Detail

```http
GET /api/v1/admin/withdrawals/{id}
```

Returns one withdrawal with `company`, `paymentMethod`, `payoutAccount` and
`paymentProvider` loaded.

### Approve (starts payout)

```http
POST /api/v1/admin/withdrawals/{id}/approve
```

No request body. Success returns `status: "processing"`:

```json
{
  "id": 42,
  "status": "processing",
  "approved_at": "2026-11-11T09:03:00+00:00",
  "approved_by": 7,
  "requires_approval": false
}
```

The backend then calls the payout provider and stores the provider reference.
A provider webhook later marks the withdrawal `completed` (or `failed` and
refunds the wallet).

Errors (422):
- `withdrawal: ["Only OTP-confirmed withdrawals can be approved."]`

### Approve manually (cash/offline, no payout provider)

Use this when WaiFai pays the client directly (cash or offline) instead of using
a payout provider. No provider API is called and no provider reference is stored.

```http
POST /api/v1/admin/withdrawals/{id}/approve-manual
Content-Type: application/json

{ "note": "Paid cash at the office, receipt #1042" }
```

- `note` optional, string, max 1000 (stored in withdrawal metadata and audit log)
- Only allowed while `status = pending_approval` and OTP-confirmed
- The requested gross `amount` is debited from the client wallet **exactly once**:
  - normal requests already debited it as a hold at request time, so this
    endpoint does not debit it again;
  - legacy withdrawals without a debit hold are debited by this endpoint.
- The withdrawal becomes `completed` immediately; `approved_at`, `approved_by`
  and `processed_at` are set. The wallet is not refunded and the withdrawal never
  enters `processing`.

Success returns `status: "completed"`:

```json
{
  "id": 42,
  "status": "completed",
  "approved_at": "2026-11-11T09:03:00+00:00",
  "approved_by": 7,
  "processed_at": "2026-11-11T09:03:00+00:00",
  "requires_approval": false
}
```

Errors (422):
- `withdrawal: ["Only OTP-confirmed withdrawals awaiting approval can be approved manually."]`
- `withdrawal: ["Insufficient wallet balance."]` (only when a missing legacy debit
  would overdraw the wallet)

### Deny (refunds wallet)

```http
POST /api/v1/admin/withdrawals/{id}/deny
Content-Type: application/json

{ "reason": "Suspicious destination number" }
```

- `reason` required, string, max 1000

Success returns `status: "denied"`, `denied_at`, `denied_by`, `denial_reason`.
The gross amount is credited back to the client's wallet.

Errors (422):
- `withdrawal: ["Only OTP-confirmed withdrawals can be denied."]`

---

## 11. UI states and copy

### Client — withdrawal list

| Status | Badge | Extra |
| --- | --- | --- |
| `pending` | "Awaiting OTP" | Show Confirm / Resend / Cancel |
| `pending_approval` | "Waiting for approval" | Explain WaiFai is reviewing; no cancel |
| `processing` | "Processing payout" | Show net amount and destination |
| `completed` | "Paid" | Show `processed_at` / provider reference if desired |
| `failed` | "Failed" | Show `failure_reason`, "Wallet refunded" |
| `denied` | "Denied" | Show `denial_reason`, "Wallet refunded" |
| `cancelled` | "Cancelled" | "Wallet refunded" |

### Client — OTP screen

- Show masked destination, gross amount, fees and net amount
- Show `otp_channel` ("Code sent to your email/SMS")
- Countdown from `otp_expires_at`
- 6-digit input, Confirm button
- Resend disabled until cooldown; show "Resend in Ns"
- On success switch to "Waiting for WaiFai approval" and stop polling for OTP

### Admin — approval queue

- Default tab: `pending_approval`
- Columns: reference, company, destination, amount, net amount, fees, confirmed
  time, waiting duration
- Row actions: **Approve** (confirm modal: "This starts the provider payout to
  {destination}"), **Approve manually** (confirm modal: "I paid this client
  directly (cash/offline); no payout provider will be called"), and **Deny**
  (modal with required reason)
- After approve, move row to `processing`; after manual approve, move to
  `completed`; after deny, move to `denied`
- Provide status tabs/counts using `GET /api/v1/withdrawals/stats` per company or
  the admin list meta; there is no global admin stats endpoint

### Polling

- While `pending_approval`: poll the detail every 15–30s, or refresh on focus
- While `processing`: poll every 15–30s until `completed`/`failed`
- Do not poll the OTP confirm endpoint; it is not idempotent and returns 422 once
  already confirmed

---

## 12. Frontend checklist

- [ ] Payout accounts CRUD + default selection
- [ ] Preview before submit; show gross, fees, net, wallet balance
- [ ] Request creates `pending` and starts OTP flow
- [ ] OTP screen with expiry countdown, attempts handling, resend cooldown
- [ ] Confirm moves to `pending_approval`; copy says "waiting for approval"
- [ ] Cancel only available in `pending`
- [ ] Client list/detail renders all statuses and denial/failure reasons
- [ ] Admin queue filters `pending_approval` by default
- [ ] Approve starts payout and shows `processing`
- [ ] Approve manually completes without a provider call and shows `completed`
- [ ] Deny requires a reason and shows wallet refunded
- [ ] Admin routes are hidden from tenant users
- [ ] Money formatted from decimal strings
- [ ] 422 field errors mapped to the correct inputs
- [ ] No client-side fee or status computation
