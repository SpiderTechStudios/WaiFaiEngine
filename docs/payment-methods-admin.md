# Admin API: payment methods (mobile-money networks)

Platform-wide mobile-money networks (M-Pesa, Airtel Money, HaloPesa, Mixx by Yas,
T-Pesa) used for network detection, payout accounts and withdrawal fees.

**Auth:** `Authorization: Bearer {sanctum token}`  
**Access:** platform admin (`platform.admin`) via `/api/v1/admin/*`, superadmin via `/api/v1/superadmin/*`  
**Tenant read-only endpoint (unchanged):** `GET /api/v1/payment-methods` (active only)

---

## List all payment methods (including inactive)

`GET /api/v1/admin/payment-methods` or `GET /api/v1/superadmin/payment-methods`

```json
{
  "status": true,
  "code": 200,
  "message": "Payment methods retrieved",
  "data": {
    "items": [
      {
        "id": 3,
        "key": "airtelmoney",
        "slug": "airtelmoney",
        "name": "Airtel Money",
        "type": "mobile_money",
        "logo_url": null,
        "prefixes": ["068", "069", "078"],
        "is_active": true,
        "sort_order": 3,
        "created_at": "2026-11-09T00:00:00+00:00",
        "updated_at": "2026-11-11T09:00:00+00:00"
      }
    ]
  }
}
```

The `key` is stable and must not be changed; it is referenced by network
aliases and stored withdrawal `network` values.

---

## Update name, prefixes and display settings

`PATCH /api/v1/admin/payment-methods/{paymentMethod}`  
`PUT` is also accepted. `{paymentMethod}` is the numeric `id`.

```json
{
  "name": "Airtel Money Tanzania",
  "prefixes": ["78", "069", "068"],
  "logo_url": "https://cdn.example.com/airtel.png",
  "is_active": true,
  "sort_order": 3
}
```

All fields are optional (`sometimes`). Only `name` and `prefixes` are commonly
needed.

- `prefixes` replaces the full list.
- Each prefix accepts `74` or `074`; it is normalized to `074`.
- Duplicates are removed and the list is sorted.
- A prefix cannot belong to two networks at once.
- `key` cannot be changed.

Response is the updated payment method object.

Errors (422):

```json
{ "status": false, "code": 422, "message": "Validation Error", "data": {
  "prefixes": ["Prefix 074 is already assigned to M-Pesa."]
} }
```

---

## Add one prefix

`POST /api/v1/admin/payment-methods/{paymentMethod}/prefixes`

```json
{ "prefix": "063" }
```

- Normalizes `63` → `063`
- Idempotent: adding an existing prefix returns `200` with `"Prefix is already configured"`
- Rejects a prefix owned by another network
- Returns the updated method with the sorted `prefixes` array

Errors: `422 data.prefix.0 = "Prefix 1234 is already assigned to ..."` or
`"Prefix must be a 3-digit Tanzania network prefix such as 074 or 068."`

---

## Remove one prefix

`DELETE /api/v1/admin/payment-methods/{paymentMethod}/prefixes/{prefix}`

Example: `DELETE /api/v1/admin/payment-methods/3/prefixes/063`

- Normalizes the path value (`63` or `063`)
- Returns the updated method
- Error `422 data.prefix.0 = "This prefix is not configured for the payment method."` if missing

---

## Superadmin

Every endpoint above is also available under `/api/v1/superadmin/payment-methods`.
`platform.admin` accepts `is_admin` or `is_superadmin`; the superadmin group
requires `is_superadmin`.

## Effects

Changes apply immediately (no cache):

- New payments/payout accounts are mapped to the network by phone prefix using
  `PaymentMethodResolver`.
- Withdrawal fee groups and network names come from the `payment_methods` row.
- Existing withdrawals keep their stored `network` value.
