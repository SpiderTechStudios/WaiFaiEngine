# Expenses API audit

Date: 7 Oct 2026. Backend: Laravel (Sanctum bearer token), base `https://api.waifai.co.tz/api/v1`.
The backend code is not in this repo or a sibling folder. Everything below comes from the
backend's published OpenAPI 3 spec (`https://api.waifai.co.tz/docs`, "WaiFai Engine API 1.0.0")
and the frontend client `src/lib/api/expenses.ts`. No live calls were made (no credentials were
available), so documented filters are **not verified live**. The payments audit found that
`GET /payments` silently ignores filters; the same check should be run here with an owner token.

Conventions to keep: JSON envelope `{ status, code, message, data }`, snake_case, list payloads
as `data.items` + `data.meta { current_page, last_page, total }`, amounts as decimal strings
(`"50000.00"`), dates `YYYY-MM-DD`, timestamps ISO-8601 UTC, company scope from the token's
current company, sorting as `sort` + `direction` (not `-field`), search param named `search`.
Frontend permission keys in use: `expenses.create`, `expenses.update`, `expenses.delete`.

## 1. What exists today

### `GET /expenses` (operationId `listExpenses`)

| Param             | Type                                   | Notes                                                    |
| ----------------- | -------------------------------------- | -------------------------------------------------------- |
| `router_id`       | integer                                | `router_id=null` or `scope=business` = business-wide     |
| `scope`           | `business` \| `router`                 |                                                          |
| `expense_type_id` | integer                                | the "category" the owner picks                           |
| `category`        | `all` \| `platform` \| `operational`   | coarse class derived from the expense type               |
| `from`, `to`      | date                                   | applied to `paid_at`                                     |
| `paid_to`         | string                                 | payee filter                                             |
| `currency`        | string                                 |                                                          |
| `search`          | string                                 | fields searched are not documented                       |
| `sort`            | `paid_at` \| `amount` \| `created_at` \| `id` |                                                   |
| `direction`       | `asc` \| `desc`                        |                                                          |
| `per_page`        | integer, default 15                    | `page` is not listed but implied by `PaginationMeta`     |

```json
{
  "status": true,
  "code": 200,
  "message": "Expenses retrieved",
  "data": {
    "items": [
      {
        "id": 1,
        "description": "Monthly internet bundle for Router #12",
        "amount": "50000.00",
        "currency": "TZS",
        "paid_at": "2026-09-25",
        "paid_to": "Airtel",
        "reference": "AIR-09252026",
        "notes": null,
        "status": "recorded",
        "source": "manual",
        "category": "operational",
        "router": { "id": 12, "name": "Mbezi Router" },
        "expense_type": {
          "id": 2, "name": "Internet", "slug": "internet", "description": null,
          "category": "operational", "is_active": true
        },
        "created_by": { "id": 4, "name": "Asha" },
        "created_at": "2026-09-25T09:12:00.000000Z",
        "updated_at": "2026-09-25T09:12:00.000000Z"
      }
    ],
    "meta": { "current_page": 1, "last_page": 1, "total": 1 }
  }
}
```

### Other endpoints

| Endpoint                                 | What it does                                                                                       |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `GET /expenses/{expense}`                | One expense, same shape as a list item                                                             |
| `POST /expenses`                         | Create. Body below. `category` and `source` derive from the type; `router_id` omitted = business-wide |
| `PATCH /expenses/{expense}`              | Update, same body (`StoreExpenseRequest`). System-generated expenses cannot be edited              |
| `DELETE /expenses/{expense}`             | Soft delete ("financial records are soft deleted to preserve history")                            |
| `GET /expenses/summary`                  | `{ total, currency, platform_total, operational_total, count, by_currency[] }`. Params: `router_id`, `expense_type_id`, `category`, `from`, `to` (no `search`, `scope`, `paid_to`) |
| `GET /expense-types`                     | Active expense types the owner can pick (plain array, not paginated)                               |
| `GET /routers/{router}/expenses`         | Router report: `router`, `summary`, `breakdown[] { expense_type_id, expense_type, category, total, count }`, paginated `expenses`. Params `from`, `to`, `category` |
| `/admin/expense-types` (+ activate/deactivate) | Platform admin CRUD of the type list (not available to company owners)                        |
| `GET /income?from&to`, `GET /income/export`    | Income report used for profit (already used by `useIncomeSummary`)                          |

Create/update body (`StoreExpenseRequest`):

```json
{
  "router_id": 12,
  "expense_type_id": 2,
  "description": "Monthly internet bundle for Router #12",
  "amount": 50000,
  "currency": "TZS",
  "paid_at": "2026-09-25",
  "paid_to": "Airtel",
  "reference": "AIR-09252026",
  "notes": null
}
```

Required: `expense_type_id`, `description`, `amount`, `paid_at`, `paid_to`.

## 2. Classification

AVAILABLE = the API returns it. DERIVABLE = the client can compute it from what the API returns.
MISSING = needs backend work.

### Expense fields

| Item                         | Status        | Notes                                                                                   |
| ---------------------------- | ------------- | --------------------------------------------------------------------------------------- |
| id, amount, currency         | **AVAILABLE** | amount is a decimal string                                                              |
| category / type              | **AVAILABLE** | `expense_type { id, name, slug }`; plus coarse `category` platform/operational          |
| description / note           | **AVAILABLE** | `description` (required) and `notes`                                                    |
| vendor / payee               | **AVAILABLE** | `paid_to` (required)                                                                    |
| paid_on                      | **AVAILABLE** | `paid_at` (date)                                                                        |
| router or business-wide      | **AVAILABLE** | `router` object or `null`                                                               |
| branch                       | DERIVABLE     | Only for router expenses, via `router.branch_id` from `GET /routers`. No branch on business-wide expenses |
| payment method               | MISSING       |                                                                                         |
| reference / receipt number   | **AVAILABLE** | `reference`                                                                             |
| receipt attachment           | MISSING       |                                                                                         |
| status (paid / scheduled)    | MISSING       | `status` exists but is always `recorded`                                                |
| recurring                    | MISSING       |                                                                                         |
| created_by                   | **AVAILABLE** | `{ id, name }`                                                                          |
| created_at, updated_at       | **AVAILABLE** |                                                                                         |
| source (manual / system)     | **AVAILABLE** | extra field: system expenses are read-only                                              |

### Lists, filters, summary

| Item                                            | Status        | Notes                                                                                   |
| ----------------------------------------------- | ------------- | --------------------------------------------------------------------------------------- |
| Categories list                                 | **AVAILABLE** | Fixed per platform (admin-managed), not per tenant. No colour, no tenant create         |
| Category colour                                 | DERIVABLE     | Fixed client palette keyed by slug                                                      |
| Server pagination                               | **AVAILABLE** | `page`, `per_page`                                                                      |
| Search (note, vendor, reference)                | **AVAILABLE** | `search`; searched fields undocumented                                                  |
| Filter: category                                | **AVAILABLE** | `expense_type_id`                                                                       |
| Filter: router / business-wide                  | **AVAILABLE** | `router_id`, `scope`                                                                    |
| Filter: payment method                          | MISSING       | field does not exist                                                                    |
| Filter: status                                  | MISSING       | field has one value                                                                     |
| Filter: date range                              | **AVAILABLE** | `from`, `to`                                                                            |
| Filter: amount range                            | DERIVABLE     | Client-side over loaded rows only                                                       |
| Sorting                                         | **AVAILABLE** | `sort` + `direction`                                                                    |
| Summary: total, count                           | **AVAILABLE** | `/expenses/summary` (not for `search` or `scope=business`)                              |
| Summary: average                                | DERIVABLE     | total ÷ count                                                                           |
| Summary: previous total, % change               | DERIVABLE     | second call/load with the previous range                                                |
| Summary: biggest category, by category          | DERIVABLE     | client grouping over all rows in range (per router only: `/routers/{id}/expenses`)      |
| Summary: by router / business-wide              | DERIVABLE     | client grouping                                                                         |
| Summary: daily / monthly series                 | DERIVABLE     | client grouping                                                                         |
| Summary: recurring monthly, upcoming, overdue   | MISSING       | needs recurring / scheduled expenses                                                    |
| Profit: income for the range                    | **AVAILABLE** | `GET /income?from&to` (`useIncomeSummary`)                                              |
| Profit: net, margin                             | DERIVABLE     | income − expenses, net ÷ income                                                         |
| Budgets                                         | MISSING       |                                                                                         |
| Receipt upload / storage                        | MISSING       |                                                                                         |
| Export CSV                                      | DERIVABLE     | client CSV over loaded rows; server export MISSING                                      |
| Bulk delete / change category                   | MISSING       |                                                                                         |
| Audit trail                                     | MISSING       | only `created_by`, `created_at`, `updated_at`; no `updated_by` or history               |

## 3. Missing items: recommended additions

Each keeps the existing Laravel conventions (envelope, snake_case, `data.items` + `meta`,
`sort` + `direction`, permission `expenses.*`, company from the token).

### 3.0 Free-text category on expenses (priority 0, blocking)

Reason: owners type their own category ("Generator fuel", "Security guard"). Today
`StoreExpenseRequest` requires `expense_type_id`, and only platform admins can create types
(`POST /admin/expense-types`), so a business can't record a cost the platform didn't foresee.

Add an optional `category_name` (string, max 60) to `StoreExpenseRequest` / `UpdateExpenseRequest`
and make `expense_type_id` optional when it is present. The server resolves it to a
company-scoped type (`firstOrCreate` on company + case-insensitive name), so no setup screen is
needed. Return the resolved `expense_type` as today, and accept `category_name` as a list filter.

Frontend workaround until then: a typed name that matches an active type uses that type; any other
name is saved under a general type (`other` / `misc` / `general`, else the first operational type)
with `Category: <name>` as the first line of `notes`. The client reads that line back as the
category and filters by category client-side.

### 3.1 Summary breakdowns (priority 1)

Reason: every chart and the category/router lists currently require downloading every expense
in the range (the client caps at 1,000 rows).

Extend `GET /expenses/summary` (accept the same params as the list, including `search`,
`scope`, `paid_to`):

```json
{
  "status": true, "code": 200, "message": "Expense summary",
  "data": {
    "from": "2026-10-01", "to": "2026-10-07", "currency": "TZS",
    "total": 350000, "count": 12, "average": 29166.67,
    "previous": { "from": "2026-09-01", "to": "2026-09-07", "total": 280000 },
    "by_expense_type": [ { "expense_type_id": 2, "name": "Internet", "slug": "internet", "total": 150000, "count": 3 } ],
    "by_router": [ { "router_id": null, "name": "Business-wide", "total": 120000, "count": 4 } ],
    "daily": [ { "date": "2026-10-01", "total": 50000, "by_expense_type": { "internet": 50000 } } ]
  }
}
```

### 3.2 Payment method (priority 2)

Reason: owners reconcile cash vs mobile money vs bank.

Add `payment_method` (`cash` | `mobile_money` | `bank` | `other`, nullable) to the expense,
to `StoreExpenseRequest`, and as a `payment_method` list filter.

### 3.3 Receipt upload (priority 3)

Reason: proof of spend for tax and partners.

```
POST /expenses/{expense}/receipt   multipart: file (jpg, png, pdf, max 5 MB)
DELETE /expenses/{expense}/receipt
```

```json
{ "status": true, "code": 200, "message": "Receipt uploaded",
  "data": { "receipt": { "url": "https://…/receipts/abc.pdf", "mime": "application/pdf", "size": 182044 } } }
```

The expense gains `receipt: { url, mime, size } | null`.

### 3.4 Server export (priority 4)

Reason: client export is capped by the rows it loaded. Mirror the existing `GET /income/export`.

`GET /expenses/export?<same filters as the list>` → `text/csv`. Columns:
`paid_at,description,expense_type,router,paid_to,payment_method,reference,amount,currency,created_by`.

### 3.5 Recurring and scheduled expenses (priority 5)

Reason: rent, internet and salaries repeat; owners need "what is due next".

Expense fields and body:

```json
{
  "status": "paid",
  "recurrence": { "frequency": "monthly", "next_due_at": "2026-11-01", "ends_at": null }
}
```

`status`: `paid` | `scheduled`. `frequency`: `weekly` | `monthly` | `yearly`. List filter
`status`. Summary adds:

```json
{ "recurring_monthly_total": 420000, "upcoming_7d": { "count": 2, "total": 90000 },
  "upcoming_30d": { "count": 5, "total": 410000 }, "overdue": { "count": 1, "total": 30000 } }
```

### 3.6 Amount range filter (priority 6)

Reason: the client can only filter amounts over loaded rows, which breaks server pagination.
Add `min_amount`, `max_amount` to `GET /expenses` and `/expenses/summary`.

### 3.7 Bulk actions (priority 7)

Reason: cleaning up imports or recategorising many rows.

```
POST /expenses/bulk-delete   { "ids": [1, 2, 3] }
POST /expenses/bulk-update   { "ids": [1, 2, 3], "expense_type_id": 4 }
```

```json
{ "status": true, "code": 200, "message": "3 expenses updated", "data": { "updated": 3, "skipped_system": 0 } }
```

### 3.8 Audit trail (priority 8)

Reason: staff can edit money records.

`GET /expenses/{expense}/activity` →
`{ "items": [ { "action": "updated", "by": { "id": 4, "name": "Asha" }, "at": "…", "changes": { "amount": ["40000.00", "50000.00"] } } ] }`.
Also add `updated_by` to the expense.

### 3.9 Budgets (priority 9, optional)

Reason: spending targets per category.

```
GET /expense-budgets?month=2026-10
PUT /expense-budgets   { "month": "2026-10", "items": [ { "expense_type_id": 2, "amount": 200000 } ] }
```

Response items: `{ expense_type_id, name, budget, spent, remaining }`.

### 3.10 Category colour and tenant categories (priority 10, optional)

Reason: consistent colours across devices, and owners asking for their own categories.
Add nullable `color` to `ExpenseType`; optionally `POST /expense-types` (company scope) for
tenant-specific types. Until then the client uses a fixed palette keyed by `slug`.

## 4. Proposed contract (adapted)

```
GET    /expenses?page=1&per_page=25&search=&expense_type_id=&router_id=&scope=business|router
              &payment_method=&status=&from=&to=&min_amount=&max_amount=&sort=paid_at&direction=desc
GET    /expenses/summary?<same filters>
GET    /expense-types
POST   /expenses                       (StoreExpenseRequest + payment_method, status, recurrence)
PATCH  /expenses/{expense}
DELETE /expenses/{expense}
POST   /expenses/{expense}/receipt     (multipart)
DELETE /expenses/{expense}/receipt
GET    /expenses/export?<same filters>
POST   /expenses/bulk-delete
POST   /expenses/bulk-update
GET    /expenses/{expense}/activity
```

List item (additions to today's shape in bold comments):

```json
{
  "id": 1, "description": "Monthly internet bundle", "amount": "50000.00", "currency": "TZS",
  "paid_at": "2026-10-01", "paid_to": "Airtel", "reference": "AIR-1001", "notes": null,
  "status": "paid", "source": "manual", "category": "operational",
  "payment_method": "mobile_money",
  "router": { "id": 12, "name": "Mbezi Router" },
  "expense_type": { "id": 2, "name": "Internet", "slug": "internet", "color": null },
  "receipt": null,
  "recurrence": { "frequency": "monthly", "next_due_at": "2026-11-01", "ends_at": null },
  "created_by": { "id": 4, "name": "Asha" }, "updated_by": null,
  "created_at": "…", "updated_at": "…"
}
```

## 5. What the frontend does until then

- Table: server pagination, `search`, `router_id` / `scope`, `from`/`to`, `sort` + `direction`.
- Category is free text (§3.0); the category filter switches the table to client paging.
- Summary blocks, charts, category and router lists, previous-period change, "Total (filtered)"
  and the amount-range filter: computed client-side over every expense matching the server
  filters (up to 1,000 rows, marked `TODO(backend)` in `src/lib/api/expenses-list.ts`).
- Profit: `useIncomeSummary` for the same range, shown only when no non-date filter is active.
- Not shown (no data): payment method, status, receipts, recurring, upcoming/overdue banner,
  budgets, bulk actions, audit history.
