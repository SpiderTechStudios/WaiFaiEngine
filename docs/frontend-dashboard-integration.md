# Frontend guide: Operator Dashboard

Use this to rebuild the operator dashboard home screen on the new `GET /dashboard` response. It now breaks revenue down into **mobile money vs voucher** for today, this month, and other periods, and adds sales, session, customer, voucher and wallet stats.

**Base URL:** `https://api.waifai.co.tz/api/v1`  
**Auth:** Sanctum bearer token + company context (same as other operations APIs)  
**Permission:** dashboard view (same as before)

---

## What changed

| Before | Now |
|--------|-----|
| `today_revenue` only counted mobile money | `revenue.today.total` = mobile money **+ voucher**, with a breakdown |
| No voucher revenue anywhere | Voucher sales are counted (see rules below) |
| Only today + all-time | Today, yesterday, this month, last month, all-time |
| `routers_online` / `routers_total` | **Removed** from the dashboard |
| "Today" started at 03:00 Tanzania time (server UTC) | "Today" / "this month" use **Africa/Dar_es_Salaam** (or the company's own timezone if set) |

The old flat fields (`today_revenue`, `today_payments`, `total_revenue`, `active_sessions`) are still returned so the current screen keeps working. **Move to the new structure and stop using them.** They will be removed later.

---

## How revenue is counted (show this logic in tooltips if useful)

| Source | When it counts | Amount | Goes to wallet? |
|--------|---------------|--------|-----------------|
| `mobile_money` | When a customer's mobile money payment is confirmed | Amount paid | **Yes**, withdrawable |
| `voucher` | The **first time** a voucher is redeemed | The voucher's package price | **No**, cash the operator collected in person |

- A multi-use voucher (e.g. `max_uses: 3`) counts as **one** sale.
- Free offers and zero-price packages are **not** revenue.
- Vouchers that were printed but never used are **not** revenue (see `vouchers.unused`).

> Important for the UI: **`wallet.balance` ≠ revenue.** The wallet only holds mobile money. Voucher money is already in the operator's hand, so it never appears in the wallet or in withdrawals.

---

## Endpoint

```http
GET /api/v1/dashboard
Authorization: Bearer {token}
Accept: application/json
```

### Response `200`

```json
{
  "status": true,
  "code": 200,
  "message": "Dashboard retrieved",
  "data": {
    "currency": "TZS",
    "timezone": "Africa/Dar_es_Salaam",
    "generated_at": "2026-10-04T14:20:11+03:00",

    "revenue": {
      "today":      { "total": 4000,   "mobile_money": 1000,   "voucher": 3000,  "mobile_money_count": 1,   "voucher_count": 2 },
      "yesterday":  { "total": 6500,   "mobile_money": 5000,   "voucher": 1500,  "mobile_money_count": 4,   "voucher_count": 1 },
      "this_month": { "total": 48000,  "mobile_money": 30000,  "voucher": 18000, "mobile_money_count": 25,  "voucher_count": 12 },
      "last_month": { "total": 120000, "mobile_money": 90000,  "voucher": 30000, "mobile_money_count": 70,  "voucher_count": 20 },
      "all_time":   { "total": 250000, "mobile_money": 190000, "voucher": 60000, "mobile_money_count": 150, "voucher_count": 40 }
    },

    "sales_today": {
      "mobile_money_payments": 1,
      "vouchers_sold": 2,
      "offers_claimed": 0
    },

    "sessions": {
      "active": 8,
      "customers_with_active_access": 6,
      "expiring_within_hour": 2
    },

    "customers": {
      "total": 120,
      "new_today": 3,
      "new_this_month": 40
    },

    "vouchers": {
      "unused": 25,
      "sold_this_month": 12
    },

    "wallet": {
      "balance": 1000,
      "pending_withdrawals_count": 0,
      "pending_withdrawals_amount": 0
    },

    "top_packages_this_month": [
      { "package_id": 3, "name": "Daily",  "sales": 12, "revenue": 18000 },
      { "package_id": 1, "name": "1 Hour", "sales": 20, "revenue": 10000 }
    ],

    "recent_sessions": [
      {
        "id": 91,
        "mac_address": "AA:BB:CC:DD:EE:FF",
        "status": "active",
        "customer": "Mteja",
        "package": "Daily",
        "started_at": "2026-10-04T10:02:00.000000Z",
        "description": "Mteja - Niawifi"
      }
    ],

    "today_revenue": 4000,
    "today_payments": 1,
    "total_revenue": 250000,
    "active_sessions": 8
  }
}
```

### Errors

| HTTP | When | UI |
|------|------|----|
| 401 | Not logged in | Existing global redirect to login |
| 403 | No permission / subscription expired | Existing "no access" / renew subscription screen |

---

## Field reference

### `revenue.{period}`: same shape for every period

| Field | Meaning |
|-------|---------|
| `total` | `mobile_money + voucher` |
| `mobile_money` | Revenue from confirmed mobile money payments |
| `voucher` | Revenue from vouchers sold (first redemption × package price) |
| `mobile_money_count` | Number of mobile money payments |
| `voucher_count` | Number of vouchers sold |

Periods (all in `data.timezone`):

| Key | Range |
|-----|-------|
| `today` | 00:00 today → now |
| `yesterday` | 00:00 → 24:00 yesterday |
| `this_month` | 1st of this month 00:00 → now |
| `last_month` | Whole previous calendar month |
| `all_time` | Everything |

### `sales_today`

| Field | Meaning |
|-------|---------|
| `mobile_money_payments` | Paid mobile money transactions today |
| `vouchers_sold` | Vouchers redeemed for the first time today |
| `offers_claimed` | Free offers claimed today (no revenue) |

### `sessions`

| Field | Meaning |
|-------|---------|
| `active` | Live hotspot sessions right now |
| `customers_with_active_access` | Customers whose package/voucher/offer time is still running (connected or not) |
| `expiring_within_hour` | Packages that end in the next 60 minutes, a good "remind to rebuy" signal |

### `customers`

| Field | Meaning |
|-------|---------|
| `total` | All customers of this company |
| `new_today` / `new_this_month` | Customers first seen in that period |

### `vouchers`

| Field | Meaning |
|-------|---------|
| `unused` | Active, not expired, never redeemed, i.e. still in stock to sell |
| `sold_this_month` | Same as `revenue.this_month.voucher_count` |

### `wallet`

| Field | Meaning |
|-------|---------|
| `balance` | Withdrawable mobile money balance |
| `pending_withdrawals_count` / `_amount` | Withdrawals requested but not paid out yet (already deducted from `balance`) |

### `top_packages_this_month`

Up to 5 packages, ordered by revenue this month (mobile money + voucher combined). Empty array when there are no sales.

### `recent_sessions`

Last 10 sessions. `customer` and `package` can be `null`. `description` is kept for the old list UI.

---

## Suggested layout

```
┌────────────────────────── Revenue ──────────────────────────┐
│ TODAY                         │ THIS MONTH                   │
│ TZS 4,000   ▲ vs yesterday    │ TZS 48,000   ▼ vs last month │
│ Mobile money  1,000  (1)      │ Mobile money 30,000 (25)     │
│ Vouchers      3,000  (2)      │ Vouchers     18,000 (12)     │
└──────────────────────────────────────────────────────────────┘
┌ Active sessions ┐ ┌ Customers w/ access ┐ ┌ Expiring < 1h ┐ ┌ New customers today ┐
│       8         │ │          6          │ │       2       │ │          3          │
└─────────────────┘ └─────────────────────┘ └───────────────┘ └─────────────────────┘
┌ Wallet (withdrawable) ┐ ┌ Vouchers in stock ┐ ┌ All-time revenue ┐
│ TZS 1,000             │ │ 25                │ │ TZS 250,000      │
│ Pending: 0            │ │ Sold this month 12│ │                  │
└───────────────────────┘ └───────────────────┘ └──────────────────┘
┌ Top packages this month ┐  ┌ Recent sessions ┐
│ Daily   12 sales  18,000│  │ ...             │
└─────────────────────────┘  └─────────────────┘
```

Ideas:
- **Mobile money vs voucher** as a split bar or donut under each revenue card.
- **Trend arrow:** compare `today.total` with `yesterday.total`, and `this_month.total` with `last_month.total` (see helper below). Note `today` is a partial day, so label it "vs yesterday (full day)".
- **Expiring < 1h** card can link to the customers list.
- **Vouchers in stock** at `0` → show a "Print more vouchers" call to action.
- Show `timezone` in small text (e.g. "Times in EAT") if you want to be explicit.

---

## TypeScript types

```ts
export interface RevenuePeriod {
  total: number;
  mobile_money: number;
  voucher: number;
  mobile_money_count: number;
  voucher_count: number;
}

export interface DashboardData {
  currency: string;               // "TZS"
  timezone: string;               // "Africa/Dar_es_Salaam"
  generated_at: string;           // ISO 8601

  revenue: {
    today: RevenuePeriod;
    yesterday: RevenuePeriod;
    this_month: RevenuePeriod;
    last_month: RevenuePeriod;
    all_time: RevenuePeriod;
  };

  sales_today: {
    mobile_money_payments: number;
    vouchers_sold: number;
    offers_claimed: number;
  };

  sessions: {
    active: number;
    customers_with_active_access: number;
    expiring_within_hour: number;
  };

  customers: {
    total: number;
    new_today: number;
    new_this_month: number;
  };

  vouchers: {
    unused: number;
    sold_this_month: number;
  };

  wallet: {
    balance: number;
    pending_withdrawals_count: number;
    pending_withdrawals_amount: number;
  };

  top_packages_this_month: Array<{
    package_id: number;
    name: string;
    sales: number;
    revenue: number;
  }>;

  recent_sessions: Array<{
    id: number;
    mac_address: string | null;
    status: string;
    customer: string | null;
    package: string | null;
    started_at: string | null;
    description: string;
  }>;

  /** @deprecated use revenue.today.total */
  today_revenue: number;
  /** @deprecated use revenue.today.mobile_money_count */
  today_payments: number;
  /** @deprecated use revenue.all_time.total */
  total_revenue: number;
  /** @deprecated use sessions.active */
  active_sessions: number;
}
```

### API call

```ts
export async function getDashboard(): Promise<DashboardData> {
  const res = await api.get<{ data: DashboardData }>('/dashboard');
  return res.data.data;
}
```

### Helpers

```ts
export const formatTZS = (value: number, currency = 'TZS') =>
  `${currency} ${Math.round(value).toLocaleString('en-US')}`;

/** Percentage change; null when there is nothing to compare against. */
export function trend(current: number, previous: number): number | null {
  if (previous === 0) return current === 0 ? 0 : null;
  return Math.round(((current - previous) / previous) * 100);
}

/** Share of voucher revenue, for a split bar / donut. */
export const voucherShare = (p: RevenuePeriod) =>
  p.total === 0 ? 0 : Math.round((p.voucher / p.total) * 100);
```

Usage:

```ts
const d = await getDashboard();
const dayTrend = trend(d.revenue.today.total, d.revenue.yesterday.total);       // e.g. -38
const monthTrend = trend(d.revenue.this_month.total, d.revenue.last_month.total);
```

---

## Migration checklist (old fields → new)

| Old | New |
|-----|-----|
| `today_revenue` | `revenue.today.total` (now includes vouchers) |
| `today_payments` | `revenue.today.mobile_money_count` (or `sales_today.mobile_money_payments`) |
| `total_revenue` | `revenue.all_time.total` |
| `active_sessions` | `sessions.active` |
| `routers_online` / `routers_total` | Removed, drop the router card |
| `recent_sessions[].description` | Prefer `customer` + `package` + `started_at` |

---

## Refresh

- Fetch on page load and when the tab regains focus.
- Optional polling every **60 s**. The response is computed live, so no cache-busting is needed.
- After actions that change money (withdrawal request/cancel, voucher creation), refetch the dashboard.

---

## Test checklist

- [ ] Redeem a voucher on the captive portal → `revenue.today.voucher` and `sales_today.vouchers_sold` go up; `wallet.balance` does **not** change
- [ ] Redeem the same multi-use voucher again → no extra voucher revenue
- [ ] Mobile money payment → `revenue.today.mobile_money` and `wallet.balance` both go up
- [ ] Claim a free offer → `sales_today.offers_claimed` goes up; revenue unchanged
- [ ] Print vouchers → `vouchers.unused` goes up
- [ ] Request a withdrawal → `wallet.balance` down, `wallet.pending_withdrawals_*` up; cancel it → back
- [ ] At 00:30 EAT, "today" shows only sales since midnight EAT
- [ ] Empty company → all zeros, `top_packages_this_month: []`, no crashes
