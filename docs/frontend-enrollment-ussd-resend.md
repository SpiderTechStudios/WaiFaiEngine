# Frontend guide: Registration USSD / STK resend

Use this document to adapt the registration UI after a customer **misses the mobile money push** on their phone.

**Base URL:** `https://api.waifai.co.tz/api/v1`  
**Auth:** none (public enrollment endpoints)

---

## What changed on the backend

Previously:

- Missed USSD → customer could not easily get another push with the same details.
- Soft-expired enrollment often looked “dead” even though payment could still be retried.

Now:

1. **Resubmitting register** with the same email / phone / domain **resumes** the enrollment and sends a **new** USSD/STK push (`200` instead of a hard “already taken” error).
2. **`POST .../retry-payment`** resends a push for an existing `enrollment_reference` (including shortly after soft expiry, within grace).
3. Poll responses include **`can_retry_payment`** and **`next_action: "retry_payment"`** so the UI can show a Resend button.

---

## Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/auth/register` | Start **or resume** enrollment + send USSD |
| `GET`  | `/auth/enrollments/{reference}/payment-status` | Poll until paid / account created |
| `POST` | `/auth/enrollments/{reference}/retry-payment` | Resend USSD without filling the form again |

---

## Recommended UX flow

```
[Registration form]
        │
        ▼
 POST /auth/register
        │
        ├─ 201 → new enrollment → Waiting screen
        ├─ 200 → resumed enrollment → Waiting screen (same reference)
        └─ 422 → show field errors
        │
        ▼
[Waiting for payment screen]
  - Show: “Check your phone for the payment request to {payment_phone}”
  - Poll GET .../payment-status every 3–5s
  - If data.can_retry_payment === true → show “Resend payment request”
        │
        ├─ Resend → POST .../retry-payment → toast + keep polling
        ├─ next_action === "login" → redirect to login
        └─ HTTP 410 → “Session expired. Please register again.” → back to form
```

Persist `enrollment_reference` in `sessionStorage` (or equivalent) when leaving the form so refresh on the waiting screen still works.

---

## 1. Register — start or resume

### Request

```http
POST /api/v1/auth/register
Content-Type: application/json
Accept: application/json
```

```json
{
  "first_name": "Jane",
  "last_name": "Doe",
  "business_name": "ABC Internet",
  "email": "jane@example.com",
  "phone": "0700123456",
  "payment_phone": "0687181497",
  "password": "Secret123!",
  "password_confirmation": "Secret123!",
  "address": "Dar es Salaam",
  "domain_name": "abc-internet"
}
```

### Success responses

| HTTP | Meaning | Frontend action |
|------|---------|-----------------|
| **201** | Brand-new enrollment | Save `data.enrollment_reference`, go to waiting screen |
| **200** | Existing incomplete enrollment **resumed**; new push sent | Same as 201 — **do not treat as error** |

Example `data` (both 200 and 201):

```json
{
  "enrollment_reference": "ENR-ABC123XYZ",
  "enrollment_status": "pending_payment",
  "payment_status": "pending",
  "amount": "10000.00",
  "currency": "TZS",
  "expires_at": "2026-09-24T08:10:00+00:00",
  "remaining_attempts": 3,
  "payment_phone": "0687181497",
  "can_retry_payment": true,
  "next_action": "retry_payment"
}
```

Messages to surface:

- **201:** `Registration submitted. Please complete the payment request on your phone.`
- **200:** `Existing registration found. A new payment request was sent to your phone.`

### Important: do not treat same-email as “already registered”

If the customer still has an incomplete enrollment that can accept payment, register returns **200** and resends the push.

Still show **422** when it is a real conflict, for example:

- Email already has a **completed** account → use login.
- Same email but **different phone** on a pending enrollment.
- Domain / phone reserved by a **different** pending registration.

---

## 2. Poll payment status

```http
GET /api/v1/auth/enrollments/{enrollment_reference}/payment-status
Accept: application/json
```

Suggested interval: **3–5 seconds**. Stop when completed or hard-expired (410).

### Drive UI from `data`, not only from `message`

| Condition | UI |
|-----------|----|
| `data.next_action === "login"` or `data.account_created === true` | Success → redirect to `/login` |
| `data.payment_status === "pending"` | Waiting spinner + phone hint |
| `data.can_retry_payment === true` | Show **Resend payment request** |
| `data.enrollment_status === "payment_failed"` | Show failure copy + Resend if `can_retry_payment` |
| `data.enrollment_status === "expired"` **and** `can_retry_payment === true` | Soft expiry — **still allow Resend** (grace window) |
| HTTP **410** | Hard expiry — back to register form |

### Example poll payload (pending, can resend)

```json
{
  "status": true,
  "code": 200,
  "message": "Waiting for payment confirmation. You can resend the payment request if you missed it.",
  "data": {
    "enrollment_reference": "ENR-ABC123XYZ",
    "enrollment_status": "pending_payment",
    "payment_status": "pending",
    "amount": "10000.00",
    "currency": "TZS",
    "expires_at": "2026-09-24T08:10:00+00:00",
    "remaining_attempts": 3,
    "payment_phone": "0687181497",
    "can_retry_payment": true,
    "next_action": "retry_payment",
    "payment": {
      "status": "pending",
      "amount": "10000.00",
      "currency": "TZS",
      "reference": "PAY-...",
      "purpose": "platform_subscription",
      "provider": "palmpesa",
      "checkout_url": null,
      "payment_instructions": {}
    }
  }
}
```

### Example poll payload (paid, account ready)

```json
{
  "status": true,
  "code": 200,
  "message": "Payment successful. Your account has been created.",
  "data": {
    "enrollment_reference": "ENR-ABC123XYZ",
    "enrollment_status": "completed",
    "payment_status": "paid",
    "account_created": true,
    "next_action": "login",
    "redirect_to": "/login",
    "can_retry_payment": false
  }
}
```

Use `data.redirect_to` when present (`"/login"`).

---

## 3. Resend USSD / STK (primary fix for “missed the push”)

```http
POST /api/v1/auth/enrollments/{enrollment_reference}/retry-payment
Content-Type: application/json
Accept: application/json
```

### Body (optional)

```json
{}
```

Or update the mobile money number:

```json
{
  "payment_phone": "0711987000"
}
```

### Responses

| HTTP | Meaning | Frontend action |
|------|---------|-----------------|
| **200** | New push sent; reservation TTL extended | Toast success; update `payment_phone` / `expires_at` from `data`; keep polling |
| **410** | Grace over or max failed attempts | “Session expired. Please register again.” → form |
| **422** | Validation | Show field errors |

Success message from API:

> Payment request resent. Please complete the payment on your phone.

### Button behaviour

- Label: **Resend payment request** / **Nilituma tena** (or similar).
- Enable when `data.can_retry_payment === true`.
- Disable while the request is in flight (avoid double taps).
- Optional: show `remaining_attempts` (“You can try again {n} more times” — this counts **failed** provider attempts, not every resend).
- Optional: small input to change `payment_phone` before resend.

---

## Waiting screen checklist (implement these)

- [ ] Store `enrollment_reference` after register (201 **or** 200).
- [ ] Poll `payment-status` until login redirect or 410.
- [ ] Show Resend when `can_retry_payment === true`.
- [ ] Call `retry-payment` on Resend; do not force a full re-register for a missed push.
- [ ] Treat register **200** as success (resume), not as “email taken”.
- [ ] If soft-expired but `can_retry_payment` is still true, keep Resend visible.
- [ ] Only on **410**, clear stored reference and send user back to the form.
- [ ] On success (`next_action === "login"`), clear stored reference and redirect.

---

## Suggested TypeScript types

```ts
export type EnrollmentStatusPayload = {
  enrollment_reference: string;
  enrollment_status:
    | 'pending_payment'
    | 'payment_failed'
    | 'processing_payment'
    | 'completed'
    | 'expired'
    | 'cancelled';
  payment_status: 'pending' | 'paid' | 'failed' | 'cancelled' | null;
  amount: string;
  currency: string;
  expires_at: string | null;
  remaining_attempts: number;
  payment_phone: string | null;
  can_retry_payment: boolean;
  next_action?: 'retry_payment' | 'login';
  account_created?: boolean;
  redirect_to?: string;
  payment?: {
    status: string;
    amount: string;
    currency: string;
    reference: string;
    purpose: string;
    provider: string;
    checkout_url?: string | null;
    payment_instructions?: Record<string, unknown>;
  };
};

export type ApiEnvelope<T> = {
  status: boolean;
  code: number;
  message: string;
  data: T;
};
```

### Minimal client helpers

```ts
async function registerEnrollment(body: RegisterBody) {
  const res = await fetch(`${API}/auth/register`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  });
  const json = await res.json();
  // Accept both 201 (new) and 200 (resumed)
  if (res.status !== 200 && res.status !== 201) throw json;
  return json as ApiEnvelope<EnrollmentStatusPayload>;
}

async function pollEnrollment(reference: string) {
  const res = await fetch(
    `${API}/auth/enrollments/${encodeURIComponent(reference)}/payment-status`,
    { headers: { Accept: 'application/json' } },
  );
  const json = await res.json();
  if (res.status === 410) return { expired: true as const, json };
  if (!res.ok) throw json;
  return { expired: false as const, json: json as ApiEnvelope<EnrollmentStatusPayload> };
}

async function resendEnrollmentPush(
  reference: string,
  paymentPhone?: string,
) {
  const res = await fetch(
    `${API}/auth/enrollments/${encodeURIComponent(reference)}/retry-payment`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(paymentPhone ? { payment_phone: paymentPhone } : {}),
    },
  );
  const json = await res.json();
  if (res.status === 410) return { expired: true as const, json };
  if (!res.ok) throw json;
  return { expired: false as const, json: json as ApiEnvelope<EnrollmentStatusPayload> };
}
```

---

## Timing / limits (for copy and edge cases)

| Setting | Default | Meaning for UI |
|---------|---------|----------------|
| Reservation TTL | **4 minutes** | `expires_at` on the enrollment; resend **extends** this |
| Payment grace | **60 minutes** after `expires_at` | Soft-expired enrollments can still resend within this window |
| Max failed attempts | **3** | After too many **failed** charges → 410, must register again |
| Register throttle | 10 / min | Avoid spam submit |
| Retry-payment throttle | 20 / min | Still debounce the button client-side |

---

## Copy suggestions (EN)

| Situation | Copy |
|-----------|------|
| After register | Check your phone and approve the payment request sent to **{payment_phone}**. |
| Missed push | Didn’t get the prompt? Tap **Resend payment request**. |
| After resend | A new payment request was sent to **{payment_phone}**. |
| Soft expiry + can retry | Your session timer ran out, but you can still resend the payment request. |
| HTTP 410 | This registration payment session has expired. Please register again. |
| Paid / account created | Payment successful. You can now log in. |

---

## What you do **not** need to change

- Login flow after `next_action === "login"`.
- Captive portal `/connect` package purchase (separate from platform registration).
- Webhook handling (server-side only).

---

## Quick QA checklist

1. Register once → USSD arrives → pay → account created → login works.  
2. Register → **ignore** USSD → tap **Resend** → new USSD → pay → account created.  
3. Register → ignore USSD → submit **same form again** → get **200** + new USSD (same reference).  
4. Wait past 4 minutes but within ~60 minutes → Resend still works.  
5. After hard expiry (410) → form required again; old reference no longer usable for retry.

---

## Support contact for API questions

Backend OpenAPI (after deploy): `https://api.waifai.co.tz/api/documentation`  
Relevant operations: `registerEnrollment`, `enrollmentPaymentStatus`, `retryEnrollmentPayment`.
