# Backend requirements: Income endpoint (`GET /income`)

**Audience:** backend developer
**Base URL:** `https://api.waifai.co.tz/api/v1`
**Auth:** Sanctum bearer token + company context (same middleware and permission as today's `GET /income`)
**Envelope:** `{ "status": true, "code": 200, "message": "...", "data": { ... } }`
**Frontend page:** `/income` — data layer `src/lib/api/income-summary.ts`, normalizer `src/lib/api/income.ts`
**Background:** `docs/income-api-audit.md` (what exists today and what is missing)

The goal: one endpoint that tells a hotspot owner **how much they made, how it compares, where it came from, when customers pay, and what they can withdraw** — for any date range.

---

## 0. Current status

`GET /income` returns only:

```json
{
  "currency": "TZS",
  "total": 100000,
  "by_source": { "mobile_money": 80000, "voucher": 20000 },
  "last_14_days": [{ "date": "2026-08-20", "total": 5000 }]
}
```

Problems:

| Problem | Effect on the page |
|---|---|
| No `from` / `to` | Only "7 days" and "14 days" can draw a chart; months show totals only (borrowed from `/dashboard`); no custom range |
| Period of `total` / `by_source` is undocumented (looks all-time) | The page can't trust it for the selected range |
| `last_14_days` has no split, no count, and skips empty days | No stacked chart, no transaction column, frontend must fill gaps |
| No fees / net / counts / breakdowns | Those cards stay hidden; the frontend downloads up to 500 payments to estimate some of them |

**Do not break the current fields.** Keep `currency`, `total`, `by_source`, `last_14_days` exactly as they are until the frontend confirms the switch (same deprecation approach as `/dashboard`).

---

## 1. Request

```http
GET /api/v1/income?from=2026-09-01&to=2026-09-30&compare=1&branch_id=&router_id=
Authorization: Bearer {token}
Accept: application/json
```

| Param | Type | Required | Default | Rule |
|---|---|---|---|---|
| `from` | `Y-m-d` | no | today − 13 days | `date_format:Y-m-d` |
| `to` | `Y-m-d` | no | today | `date_format:Y-m-d`, `after_or_equal:from`, not in the future |
| `compare` | bool | no | `0` | When `1`, include `previous` (same length, immediately before `from`) |
| `branch_id` | int | no | — | Must belong to the current company |
| `router_id` | int | no | — | Must belong to the current company (and to `branch_id` if both sent) |

- Max range **366 days** → `422` otherwise.
- Dates are interpreted in the **company time zone** (`company.timezone`, fallback `Africa/Dar_es_Salaam`), same rule as `/dashboard`. `from` = 00:00:00, `to` = 23:59:59 local.

Suggested Laravel validation (FormRequest):

```php
return [
    'from'      => ['nullable', 'date_format:Y-m-d'],
    'to'        => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
    'compare'   => ['nullable', 'boolean'],
    'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
    'router_id' => ['nullable', 'integer', Rule::exists('routers', 'id')->where('company_id', $companyId)],
];
```

(Adjust table names to the real schema — e.g. `network_stations` if branches live there.)

---

## 2. Response `200`

```json
{
  "status": true,
  "code": 200,
  "message": "Income retrieved",
  "data": {
    "range": {
      "from": "2026-09-01",
      "to": "2026-09-30",
      "timezone": "Africa/Dar_es_Salaam",
      "currency": "TZS"
    },
    "generated_at": "2026-10-04T14:20:11+03:00",

    "totals": {
      "gross": 100000,
      "fees": 3500,
      "net": 96500,
      "mobile_money": 80000,
      "vouchers": 20000,
      "pending": 2000,
      "refunds": 0,
      "paid_out": 50000,
      "available_balance": 26500,
      "pending_withdrawals": 5000
    },

    "counts": {
      "paid": 62,
      "mobile_money_paid": 50,
      "vouchers_sold": 12,
      "pending": 3,
      "failed": 9,
      "initiated": 62,
      "avg_ticket": 1613
    },

    "previous": {
      "from": "2026-08-02",
      "to": "2026-08-31",
      "gross": 82000,
      "net": 79000,
      "mobile_money": 70000,
      "vouchers": 12000,
      "paid": 51,
      "daily": [{ "date": "2026-08-02", "total": 3000 }]
    },

    "daily": [
      { "date": "2026-09-01", "mobile_money": 3000, "vouchers": 1000, "total": 4000, "count": 5 },
      { "date": "2026-09-02", "mobile_money": 0, "vouchers": 0, "total": 0, "count": 0 }
    ],

    "by_provider": [
      { "provider": "mpesa", "amount": 52000, "count": 30 },
      { "provider": "mixx", "amount": 18000, "count": 12 },
      { "provider": "airtel", "amount": 10000, "count": 8 }
    ],
    "by_package": [
      { "package_id": 3, "name": "Daily", "amount": 45000, "count": 30, "mobile_money": 30000, "vouchers": 15000 }
    ],
    "by_router": [
      { "router_id": 4, "name": "Kariakoo AP", "branch_id": 2, "branch": "Kariakoo", "amount": 40000, "count": 25 }
    ],
    "by_hour": [
      { "hour": 0, "amount": 0, "count": 0 },
      { "hour": 20, "amount": 9000, "count": 7 }
    ],

    "customers": { "new": 12, "returning": 30 },

    "vouchers": { "used": 12, "unused": 25 },

    "currency": "TZS",
    "total": 100000,
    "by_source": { "mobile_money": 80000, "voucher": 20000 },
    "last_14_days": [{ "date": "2026-09-17", "total": 4000 }]
  }
}
```

The last four fields are the **legacy** ones — keep them unchanged for now.

---

## 3. Field rules (how to calculate)

Use the **same revenue rules as `/dashboard`** so both screens always agree:

| Source | Counts when | Amount |
|---|---|---|
| Mobile money | Payment status becomes `paid` (use `paid_at` for the date) | `payments.amount` |
| Voucher | **First** redemption of the voucher (use first redemption time) | The voucher's package price |

Free offers and zero-price packages are **not** income. Multi-use vouchers count **once**.

### `range`
Echo what was actually applied after defaults (frontend labels exactly this).

### `totals`

| Field | Rule |
|---|---|
| `gross` | `mobile_money + vouchers` in range |
| `mobile_money` | Sum of paid mobile money in range |
| `vouchers` | Sum of voucher sales in range |
| `fees` | Gateway/platform fees for the paid mobile money in range. `0` if fees are not charged; **omit** (or `null`) if the system can't know |
| `net` | `gross − fees − refunds` |
| `pending` | Sum of mobile money payments still `pending`/`processing` created in range |
| `refunds` | Reversed/refunded amounts in range; `0` until refunds exist |
| `paid_out` | Withdrawals **completed** in range (not all-time) |
| `available_balance` | Current wallet balance (same as `dashboard.wallet.balance`; not range-dependent) |
| `pending_withdrawals` | Current pending withdrawal amount (same as `dashboard.wallet.pending_withdrawals_amount`) |

### `counts`

| Field | Rule |
|---|---|
| `mobile_money_paid` | Paid mobile money payments in range |
| `vouchers_sold` | Vouchers first redeemed in range |
| `paid` | `mobile_money_paid + vouchers_sold` |
| `pending` | Mobile money payments `pending`/`processing` created in range |
| `failed` | Mobile money payments `failed`/`expired`/`cancelled` created in range |
| `initiated` | All mobile money payments created in range (any status) — frontend computes conversion = `mobile_money_paid / initiated` |
| `avg_ticket` | `gross / paid` (0 when `paid` = 0), rounded to whole TZS |

### `previous` (only when `compare=1`)
Same length as the range, ending the day before `from` (e.g. Sep 1–30 → Aug 2–31). Same rules as above. Include `daily` totals so the chart can draw a dashed comparison line.

### `daily`
- **One row per day from `from` to `to`, including zero days**, ordered ascending.
- `count` = `mobile_money_paid + vouchers_sold` for that day.
- `total` = `mobile_money + vouchers`.

### `by_provider`
Group paid mobile money by **mobile network**, not by aggregator. Use a stable lowercase key; the frontend formats the name:

| Key | Shown as |
|---|---|
| `mpesa` | M-Pesa |
| `mixx` | Mixx by Yas |
| `tigo` | Tigo Pesa (legacy, if older payments still say Tigo) |
| `airtel` | Airtel Money |
| `halopesa` | HaloPesa |

If the network is unknown for a payment, use `"other"`. If the network can't be determined at all, return `[]` (the card is hidden).

### `by_package`
All packages with income in range, sorted by `amount` desc. `amount` includes both sources; `mobile_money`/`vouchers` optional split.

### `by_router`
Group by the router the payment/voucher was used on (the router of the captive session). `branch` = the router's branch name or `null`. Return `[]` if the link doesn't exist yet — don't guess.

### `by_hour`
24 rows (`hour` 0–23, company time zone), both sources, zero hours included.

### `customers`
- `new` = customers whose **first** paid purchase is in range.
- `returning` = customers with a paid purchase in range **and** at least one before `from`.

### `vouchers`
- `used` = vouchers first redeemed in range (= `counts.vouchers_sold`).
- `unused` = current stock (same as `dashboard.vouchers.unused`).

### `generated_at`
Server time when the response was built (ISO 8601 with offset).

---

## 4. Filters

- `branch_id` / `router_id` filter **everything** range-based (`totals` except wallet fields, `counts`, `daily`, breakdowns, `previous`).
- Wallet fields (`available_balance`, `pending_withdrawals`, `paid_out`) stay **company-wide** — the wallet isn't per router.
- If the company has only one branch/router, the frontend won't show the filter; no special handling needed.

---

## 5. Export endpoint

```http
GET /api/v1/income/export?from=2026-09-01&to=2026-09-30&format=csv
GET /api/v1/income/export?from=2026-09-01&to=2026-09-30&format=pdf
```

- Same auth, validation and filters as `GET /income`; `format` required, `in:csv,pdf`.
- Response is a file, **not** the JSON envelope:
  - `Content-Type: text/csv; charset=utf-8` or `application/pdf`
  - `Content-Disposition: attachment; filename="income-2026-09-01_2026-09-30.csv"`
- CSV columns: `date,mobile_money,vouchers,total,transactions` (one row per day) + a final `TOTAL` row.
- PDF: company name, range, time zone, the totals block (gross, fees, net, mobile money, vouchers, paid out), and the daily table.
- Errors still use the JSON envelope (`401`, `403`, `422`).

---

## 6. Errors

| HTTP | When | Body |
|---|---|---|
| 401 | Not logged in | standard envelope |
| 403 | No permission / subscription expired | standard envelope |
| 422 | Bad `from`/`to`/`branch_id`/`router_id`/range > 366 days | standard envelope, Laravel validation map in `data` |

---

## 7. Implementation notes (Laravel)

1. **Reuse `/dashboard` logic.** The revenue rules already exist in `DashboardController@show` (mobile money on `paid_at`, voucher on first redemption, company time zone). Extract them into a service (e.g. `App\Services\IncomeReport`) and call it from both controllers so numbers can never disagree.
2. **Time zone:** convert the local range to UTC once:
   ```php
   $tz   = $company->timezone ?: 'Africa/Dar_es_Salaam';
   $from = Carbon::parse($request->from ?? now($tz)->subDays(13)->toDateString(), $tz)->startOfDay()->utc();
   $to   = Carbon::parse($request->to   ?? now($tz)->toDateString(), $tz)->endOfDay()->utc();
   ```
   Group by local day with `DATE(CONVERT_TZ(paid_at, '+00:00', '+03:00'))` (MySQL) or do the grouping in PHP after fetching the summed rows.
3. **Fill zero days** in PHP with a `CarbonPeriod` loop so `daily` is continuous.
4. **Performance:** one grouped query per source (payments, voucher redemptions) for `daily`, `by_hour`, `by_package`, `by_provider`; reuse those results for `totals` and `counts`. Index `payments(company_id, status, paid_at)` and the voucher redemption timestamp.
5. **Company scoping:** every query filtered by the current company (`CompanyContext`), same as other operations endpoints.
6. **Provider key:** store/derive the network on the payment (from the gateway callback or the payer MSISDN prefix) rather than the aggregator name (`palmpesa` etc.).

---

## 8. Test checklist

- [ ] No params → `range` = last 14 days ending today (company tz); legacy fields unchanged.
- [ ] `daily` has exactly `to − from + 1` rows, zero days included, ascending.
- [ ] `sum(daily.total) == totals.gross`; `totals.gross == mobile_money + vouchers`.
- [ ] Same range as `/dashboard` `this_month` → identical `gross`, `mobile_money`, `vouchers`, counts.
- [ ] Mobile money payment paid at 23:30 EAT appears on that local day, not the next.
- [ ] Multi-use voucher redeemed 3 times counts once, on its first redemption day.
- [ ] Free offer claim → no change to any total.
- [ ] `compare=1` → `previous` covers the same number of days, ending the day before `from`.
- [ ] `branch_id` of another company → `422`.
- [ ] Range > 366 days → `422`; `to` before `from` → `422`.
- [ ] Withdrawal completed in range → `totals.paid_out` up; `available_balance` matches `/dashboard`.
- [ ] `/income/export?format=csv` downloads a file whose TOTAL row equals `totals.gross`.
- [ ] Switching company changes every number; another company's data never appears.

---

## 9. Rollout

1. Ship sections 1–3 (range, `daily`, `totals`, `counts`, `previous`) — this unlocks month/custom ranges and the trend badge.
2. Then `by_package`, `by_hour`, `customers`, `vouchers`.
3. Then `by_provider`, `by_router` + filters (may need schema work).
4. Then `/income/export`.
5. Tell the frontend when each step is live; only `useIncomeSummary` needs updating. After the frontend switches, the legacy fields can be removed.
