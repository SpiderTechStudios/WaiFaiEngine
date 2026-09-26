# Road to Expenses — WaiFai Frontend Integration Guide

This document tells the **frontend developer** how to integrate the new **Expenses** module
(user side) and **Expense Types** management (admin side) into the WaiFai application.

It lists every endpoint, what it does, what to send, what comes back, where it lives in the
navigation, and how filtering must behave on each view.

Placeholders:

| Placeholder | Value |
|---|---|
| `{API}` | Backend base URL, e.g. `https://waifai.shereheyangu.com` |
| `{API}/api/v1` | All endpoints below are under this prefix |

> **Backend is already deployed.** The frontend only needs to consume the API. Do not invent new
> endpoints or send derived fields (see “Never send these” below).

---

## 1. Navigation placement (required)

Add a top-level **Expenses** item to the sidebar **immediately after “Payments”**.

```
Dashboard
Routers
Packages
Vouchers
Customers
Payments
Expenses            <-- NEW (put it here, right after Payments)
  ├── All Expenses
  ├── Expense Summary / Dashboard
  └── Expense Types        (admin only)
Settings
```

- The **Expenses** menu is visible when the user has the `expenses.view` permission.
- The **Expense Types** sub-item is visible only to platform admins (admin area).
- Suggested routes: `/expenses`, `/expenses/summary`, `/admin/expense-types`.

---

## 2. Authentication, tenancy and response envelope

All user endpoints require a Sanctum bearer token:

```http
Authorization: Bearer <token>
Accept: application/json
```

The current **company/workspace** is taken from the logged-in user’s selected company
(`current_company_id`). You do **not** send a `company_id` — every expense is automatically
scoped to the active company. To switch company, use the existing company-switch endpoint, then
reload expenses.

Every response uses the existing envelope:

```json
{ "status": true, "code": 200, "message": "Expenses retrieved", "data": { } }
```

Errors:

```json
{ "status": false, "code": 422, "message": "Validation Error", "data": { "amount": ["The amount must be greater than 0."] } }
```

| HTTP | Meaning | Frontend action |
|---|---|---|
| `401` | Not authenticated | Redirect to login |
| `403` | No permission, or **system-generated expense** edit/delete | Show “not allowed”; hide action when possible |
| `404` | Not found **or** belongs to another company | Show generic “not found” |
| `422` | Validation error | Map `data` keys to form field errors |

List endpoints are paginated in the existing format:

```json
{
  "data": {
    "items": [ /* records */ ],
    "meta": { "current_page": 1, "last_page": 3, "total": 42 }
  }
}
```

Use `?per_page=15` (default 15) to control page size.

---

## 3. Permissions (gate the UI)

Permissions are returned by the existing `GET {API}/api/v1/auth/me` → `data.permissions`.
Use them to show/hide buttons.

| Permission | Allows |
|---|---|
| `expenses.view` | See the Expenses menu, list, detail, summary, router report |
| `expenses.create` | “Add expense” button, `POST /expenses` |
| `expenses.update` | Edit button, `PATCH /expenses/{id}` |
| `expenses.delete` | Delete button, `DELETE /expenses/{id}` |

Role defaults seeded by the backend:

| Role | view | create | update | delete |
|---|---|---|---|---|
| owner | ✅ | ✅ | ✅ | ✅ |
| manager | ✅ | ✅ | ✅ | ✅ |
| operator | ✅ | ✅ | — | — |
| cashier | ✅ | — | — | — |

Expense **types** are managed by platform admins only (`is_admin`/`is_superadmin`), not by company
users.

---

## 4. User side — endpoints

| # | Method | Endpoint | What it does |
|---|---|---|---|
| 1 | GET | `/api/v1/expense-types` | List **active** expense types the user can pick from (read-only) |
| 2 | GET | `/api/v1/expenses` | Paginated expense list with filters/sorting |
| 3 | POST | `/api/v1/expenses` | Create an expense |
| 4 | GET | `/api/v1/expenses/{id}` | Single expense with type, router, creator |
| 5 | PATCH *(or PUT)* | `/api/v1/expenses/{id}` | Update an expense |
| 6 | DELETE | `/api/v1/expenses/{id}` | Soft-delete an expense |
| 7 | GET | `/api/v1/expenses/summary` | Aggregated totals (platform vs operational) |
| 8 | GET | `/api/v1/routers/{router}/expenses` | Expense report for one router |

### 4.1 List active expense types

```
GET {API}/api/v1/expense-types
```

Returns active types only (inactive types are hidden automatically). Load this once and cache it
for the create/edit form dropdown and filter dropdown.

```json
{
  "status": true, "code": 200, "message": "Expense types retrieved",
  "data": [
    { "id": 2, "name": "Internet", "slug": "internet", "description": null, "category": "operational", "is_active": true },
    { "id": 1, "name": "Platform Subscription", "slug": "platform-subscription", "description": null, "category": "platform", "is_active": true }
  ]
}
```

### 4.2 List expenses

```
GET {API}/api/v1/expenses
```

Query parameters (all optional):

| Param | Type | Notes |
|---|---|---|
| `router_id` | integer \| `null` | Filter by router. `router_id=null` returns business-wide expenses |
| `scope` | `business` \| `router` | Explicit alternative to `router_id=null`. `business` = no router, `router` = has a router |
| `expense_type_id` | integer | Filter by expense type |
| `category` | `all` \| `platform` \| `operational` | `all` (or omitted) = no filter. Any other value is ignored |
| `from` | date `YYYY-MM-DD` | `paid_at >= from` |
| `to` | date `YYYY-MM-DD` | `paid_at <= to` |
| `paid_to` | string | Partial match (e.g. `Airtel`) |
| `currency` | string(3) | Exact match, e.g. `TZS` |
| `source` | `manual` \| `system` | Manual vs system-generated |
| `search` | string | Matches description, paid_to, reference, notes |
| `sort` | `paid_at` \| `amount` \| `created_at` \| `id` | Default `paid_at`. Anything else falls back to `paid_at` |
| `direction` | `asc` \| `desc` | Default `desc` |
| `per_page` | integer | Default 15 |

Example:

```
GET {API}/api/v1/expenses?category=operational&router_id=12&from=2026-09-01&to=2026-09-30&sort=amount&direction=desc&page=1
```

Item shape:

```json
{
  "id": 15,
  "description": "Monthly internet bundle for Router #12",
  "amount": "50000.00",
  "currency": "TZS",
  "paid_at": "2026-09-25",
  "paid_to": "Airtel",
  "reference": "AIR-09252026",
  "notes": "Monthly data package",
  "status": "recorded",
  "source": "manual",
  "category": "operational",
  "router": { "id": 12, "name": "Mbezi Router" },
  "expense_type": { "id": 2, "name": "Internet", "slug": "internet", "category": "operational", "is_active": true },
  "created_by": { "id": 5, "name": "Jane Doe" },
  "created_at": "2026-09-25T10:00:00.000000Z",
  "updated_at": "2026-09-25T10:00:00.000000Z"
}
```

> `amount` is a **string** (`"50000.00"`) because the backend stores money as decimal. Use
> `parseFloat(amount)` only for display/maths on the client. `paid_at` is a plain date
> (`YYYY-MM-DD`). `router` is `null` for business-wide expenses.

### 4.3 Create an expense

```
POST {API}/api/v1/expenses
Content-Type: application/json
```

```json
{
  "router_id": 12,
  "expense_type_id": 4,
  "description": "Monthly internet bundle for Router #12",
  "amount": 50000,
  "currency": "TZS",
  "paid_at": "2026-09-25",
  "paid_to": "Airtel",
  "reference": "AIR-09252026",
  "notes": "Monthly data package"
}
```

| Field | Required | Rules |
|---|---|---|
| `router_id` | no | Must be a router belonging to the current company. Omit or `null` for business-wide |
| `expense_type_id` | yes | Must exist and be **active** |
| `description` | yes | string, max 255 — the **particulars** (what was paid for) |
| `amount` | yes | number **greater than 0** |
| `currency` | no | string size 3; defaults to platform currency (`TZS`) |
| `paid_at` | yes | valid date |
| `paid_to` | yes | string, max 255 |
| `reference` | no | string, max 255 |
| `notes` | no | string, max 2000 |

Returns `201` with the created expense (same shape as list item).

> **Category is derived automatically** from the selected expense type. Do **not** send `category`.

### 4.4 Get one expense

```
GET {API}/api/v1/expenses/{id}
```

Returns the full expense including `expense_type`, `router`, `created_by`. `404` if it belongs to
another company.

### 4.5 Update an expense

```
PATCH {API}/api/v1/expenses/{id}
Content-Type: application/json
```

Send only the fields you want to change (any subset of the create fields). `PUT` also works.

- Changing `expense_type_id` automatically recomputes `category`.
- To **clear** the router (make it business-wide), send `"router_id": null`.
- System-generated expenses (`source: "system"`) return **403** and cannot be edited.

```json
{ "amount": 55000, "router_id": null }
```

### 4.6 Delete an expense

```
DELETE {API}/api/v1/expenses/{id}
```

Soft delete: the record is retained in the database for financial history but disappears from all
list/summary endpoints. There is no restore endpoint. Returns:

```json
{ "status": true, "code": 200, "message": "Expense deleted", "data": [] }
```

### 4.7 Expense summary (dashboard cards)

```
GET {API}/api/v1/expenses/summary
```

Accepts the same major filters: `router_id`, `expense_type_id`, `category`, `from`, `to`
(and also `currency`, `paid_to`, `search`, `source`).

Single-currency result:

```json
{
  "total": 350000,
  "currency": "TZS",
  "platform_total": 49999,
  "operational_total": 300001,
  "count": 12,
  "by_currency": [
    { "currency": "TZS", "total": 350000, "platform_total": 49999, "operational_total": 300001, "count": 12 }
  ]
}
```

Multiple currencies (no FX conversion is performed):

```json
{
  "total": null,
  "currency": null,
  "platform_total": null,
  "operational_total": null,
  "count": 14,
  "by_currency": [
    { "currency": "TZS", "total": 350000, "platform_total": 49999, "operational_total": 300001, "count": 12 },
    { "currency": "USD", "total": 40, "platform_total": 0, "operational_total": 40, "count": 2 }
  ]
}
```

> If `total` is `null` and `by_currency` has more than one row, show the breakdown per currency
> instead of a single combined number.

Use this endpoint to build the dashboard cards:

- **Total Expenses** → `total` (or `by_currency`)
- **Platform Expenses** → `platform_total`
- **Operational Expenses** → `operational_total`
- **This Month / This Year** → call with `from`/`to` set to the current month/year
- **Expenses over time** → call per range and chart the `total` values

### 4.8 Expenses per router

```
GET {API}/api/v1/routers/{router}/expenses
```

Accepts the same filters. Returns the router, totals, a breakdown per expense type, and the
paginated expenses for that router.

```json
{
  "status": true, "code": 200, "message": "Router expenses retrieved",
  "data": {
    "router": { "id": 12, "name": "Mbezi Router" },
    "summary": { "total": 250000, "currency": "TZS", "platform_total": 0, "operational_total": 250000, "count": 4, "by_currency": [ /* ... */ ] },
    "breakdown": [
      { "expense_type_id": 2, "expense_type": "Internet", "category": "operational", "total": 100000, "count": 2 },
      { "expense_type_id": 3, "expense_type": "Electricity", "category": "operational", "total": 50000, "count": 1 }
    ],
    "expenses": [ /* Expense items */ ],
    "meta": { "current_page": 1, "last_page": 1, "total": 4 }
  }
}
```

`404` if the router does not belong to the current company. Use the existing `GET /routers` to
populate the router picker/filter.

---

## 5. Filtering on each view (required behaviour)

### 5.1 All Expenses list

Expose a filter bar wired to the list endpoint:

| Control | Query param |
|---|---|
| Router dropdown (from `GET /routers`) + “Business-wide” option | `router_id=<id>` / `router_id=null` (or `scope=business`) |
| Expense type dropdown (from `GET /expense-types`) | `expense_type_id` |
| Category tabs/segments: All / Platform / Operational | `category=all|platform|operational` |
| Date range (from–to) | `from`, `to` |
| Paid-to text | `paid_to` |
| Currency | `currency` |
| Search box | `search` |
| Column sort + order | `sort`, `direction` |
| Page size / page | `per_page`, `page` |

- Default view: no filters, `sort=paid_at&direction=desc`.
- “Business-wide” must send `router_id=null` (or `scope=business`).
- Reset button clears all params and reloads.

### 5.2 Summary / dashboard

Use the same filter controls as the list (at minimum: router, expense type, category, date range)
and pass them to `GET /expenses/summary`. The cards and any chart must update when filters change.

### 5.3 Router detail → Expenses tab

When a user opens a router, add an **Expenses** tab that calls
`GET /routers/{router}/expenses`. Show:

- Summary total + currency (parse the decimals for display).
- “Total by expense type” list from `breakdown`.
- Paginated expense list from `expenses`, with date/type filters sent as query params
  (`from`, `to`, `expense_type_id`, `category`).

### 5.4 Admin → Expense Types

Filter bar on the admin table: `category`, `is_active`, `search`, `per_page`.

---

## 6. Admin side — Expense Types management

Admin endpoints live under `/admin` and are protected by the platform-admin middleware. A normal
company user receives `403`.

| Method | Endpoint | What it does |
|---|---|---|
| GET | `/api/v1/admin/expense-types` | Paginated list of **all** types (active + inactive) |
| POST | `/api/v1/admin/expense-types` | Create a type |
| GET | `/api/v1/admin/expense-types/{id}` | Show a type |
| PATCH *(or PUT)* | `/api/v1/admin/expense-types/{id}` | Update a type |
| DELETE | `/api/v1/admin/expense-types/{id}` | Delete a type **only if unused** |
| POST | `/api/v1/admin/expense-types/{id}/activate` | Activate |
| POST | `/api/v1/admin/expense-types/{id}/deactivate` | Deactivate |

### 6.1 List (admin)

```
GET {API}/api/v1/admin/expense-types?category=operational&is_active=true&search=internet
```

Item shape:

```json
{ "id": 2, "name": "Internet", "slug": "internet", "description": null, "category": "operational", "is_active": true, "created_at": "...", "updated_at": "..." }
```

### 6.2 Create / update

```json
{ "name": "Security Services", "description": "On-site security", "category": "operational", "is_active": true }
```

| Field | Required on create | Rules |
|---|---|---|
| `name` | yes | max 255; slug is generated/updated automatically on the backend |
| `description` | no | max 2000 |
| `category` | yes | `platform` or `operational` |
| `is_active` | no | boolean, defaults to `true` |

### 6.3 Activate / deactivate / delete rules (important for UI)

- **Deactivate** (`is_active=false`): the type stays on historical expenses but no longer appears
  in `GET /expense-types` and cannot be selected for **new** expenses.
- **Delete**: allowed **only when the type is not used by any expense**. If it is used, the API
  returns `422` with `data.expense_type` message. The UI must then prompt the admin to
  **deactivate instead of delete**.
- Admin table should show a status badge and an Activate/Deactivate toggle. Disable/soft-hide the
  Delete action when the type is in use (or let the `422` guide the user).

Deactivate example response:

```json
{ "status": true, "code": 200, "message": "Expense type deactivated", "data": { "id": 2, "name": "Internet", "is_active": false } }
```

---

## 7. Never send these fields

These are derived server-side and are **rejected** if sent on expense create/update:

| Field | Reason |
|---|---|
| `category` | Derived from the selected `expense_type_id` |
| `source` | Set by the backend (`manual` / `system`) |
| `company_id` | Taken from the authenticated user’s active company |

---

## 8. Suggested UI structure

**User — Add/Edit Expense form**

- Router select (optional; include a “No router / business-wide” option) → `router_id`
- Expense type select (required, from `GET /expense-types`) → `expense_type_id`
- Description / particulars (required, textarea/input) → `description`
- Amount (required, numeric > 0) → `amount`
- Currency (default `TZS`) → `currency`
- Paid on (date picker, required) → `paid_at`
- Paid to (required, text) → `paid_to`
- Reference / receipt no (optional) → `reference`
- Notes (optional) → `notes`

Edit mode: pre-fill from `GET /expenses/{id}`. If `source === "system"`, hide/disable Save and
Delete (the API returns `403` anyway).

**User — Expenses list**

Columns: Paid on, Description, Expense type, Router (or “Business-wide”), Paid to, Amount +
Currency, Category badge, Source badge (hide `system` edit/delete), Actions (view/edit/delete
gated by permissions).

**User — Summary**

Cards: Total, Platform, Operational (+ optional This Month / This Year using `from`/`to`) and a
“by type” / “by router” chart driven by the summary and router endpoints.

**Admin — Expense Types**

Table: Name, Category, Active badge, Created; row actions Edit, Activate/Deactivate, Delete
(delete shows the “in use — deactivate instead” message on `422`).

---

## 9. Quick reference — all endpoints

### User (requires `expenses.*` permissions)

| Method | Endpoint |
|---|---|
| GET | `/api/v1/expense-types` |
| GET | `/api/v1/expenses` |
| POST | `/api/v1/expenses` |
| GET | `/api/v1/expenses/{id}` |
| PATCH / PUT | `/api/v1/expenses/{id}` |
| DELETE | `/api/v1/expenses/{id}` |
| GET | `/api/v1/expenses/summary` |
| GET | `/api/v1/routers/{router}/expenses` |

### Admin (platform admin)

| Method | Endpoint |
|---|---|
| GET | `/api/v1/admin/expense-types` |
| POST | `/api/v1/admin/expense-types` |
| GET | `/api/v1/admin/expense-types/{id}` |
| PATCH / PUT | `/api/v1/admin/expense-types/{id}` |
| DELETE | `/api/v1/admin/expense-types/{id}` |
| POST | `/api/v1/admin/expense-types/{id}/activate` |
| POST | `/api/v1/admin/expense-types/{id}/deactivate` |

Interactive API docs: `{API}/api/v1/documentation` (Swagger UI). See the **Expenses** and
**Expense Types** tags.
