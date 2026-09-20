# Agent API guide (MSP Platform)

Stable JSON API for AI agents and integrations. Do **not** scrape the Filament UI.

> **Customer portal (`/portal`)** is for **humans** (Contact persons) via magic-link e-mail login. It is **not** part of the Agent API. Do not use portal sessions or scrape `/portal` from agents — use Sanctum Bearer tokens against `/api/v1` only. See [docs/PORTAL.md](docs/PORTAL.md).

## Prerequisites

```bash
composer update laravel/sanctum stripe/stripe-php
php artisan migrate
```

`laravel/sanctum` and `stripe/stripe-php` are declared in `composer.json`. Run the update once after pulling this branch if `vendor/` is not yet installed.

## Base URL

```
{APP_URL}/api/v1
```

OpenAPI: [`openapi/agent-api.yaml`](openapi/agent-api.yaml)

## Create a token

Create, list and revoke tokens **from the admin UI** (System → API tokens) or
during first-run onboarding. Admins can also issue/revoke tokens per user under
Users. The plaintext value is shown **once**.

Prefer the self-service API when you already have a Bearer token
(`POST /api/v1/tokens` — see [API tokens](#api-tokens-own-only)).

There is no tinker step. To reset: Settings → “Revoke all API tokens”
or per-user “Revoke tokens”.

## Authenticate

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

## Roles (same intent as Filament)

| Role | Read | Create / Update | Delete |
|------|------|-----------------|--------|
| `viewer` | yes | no | no |
| `sales` | yes | yes | no |
| `manager` | yes | yes | yes |
| `admin` | yes | yes | yes |

Writes use `User::canManageContracts()` (admin/manager/sales). Deletes use `User::canAdminister()` (admin/manager).

Token self-service (`/api/v1/tokens`) is **own-tokens-only**: any authenticated user with a role may list/create/revoke **their own** Sanctum tokens (plaintext returned once on create). Managing other users' tokens is not exposed.

## Resources

| Resource | Path |
|----------|------|
| Dashboard | `GET /api/v1/dashboard` |
| Companies | `/api/v1/companies` |
| Contacts | `/api/v1/contacts` (+ nested under companies) |
| Contracts | `/api/v1/contracts` |
| Upcoming renewals | `GET /api/v1/contracts/upcoming-renewals` |
| Products | `/api/v1/products` |
| Product components (BOM) | `/api/v1/product-components` (+ nested under products) |
| Purchase bundles | `/api/v1/purchase-bundles` |
| Vendors | `/api/v1/vendors` |
| API tokens (own) | `/api/v1/tokens` |

REST: `GET` index/show, `POST` store, `PUT`/`PATCH` update, `DELETE` destroy (where applicable).

### Common index params

| Param | Description |
|-------|-------------|
| `search` | Case-insensitive text search (fields vary by resource) |
| `per_page` | Page size (default 15, max 100) |
| `page` | Page number (Laravel paginator) |
| `sort` | Column name from the resource allowlist |
| `order` | `asc` or `desc` (default `asc` when `sort` is set) |

### Dashboard (`GET /api/v1/dashboard`)

Read-only portfolio metrics. Formulas match Filament `PortfolioStats` / `UpcomingRenewals`:

| Field | Meaning |
|-------|---------|
| `active_contracts_count` | Contracts with `status=active` |
| `arr` | Sum of `Contract::annual_revenue` over active contracts |
| `mrr` | `arr / 12` |
| `annual_margin` | Sum of `Contract::annual_margin` over active contracts |
| `upcoming_renewals_30d_count` | Active contracts with `renewal_date` in the next 30 days |
| `upcoming_renewals` | Short list (≤25) of active renewals in the next **60** days (widget horizon) |
| `upcoming_notice_deadlines` | Active contracts whose computed `notice_deadline` falls within 60 days |

Do **not** invent alternate MRR/ARR math; use these fields or the same model accessors.

### Contacts

Top-level CRUD: `/api/v1/contacts`

Nested convenience (same RBAC):

- `GET /api/v1/companies/{company}/contacts`
- `POST /api/v1/companies/{company}/contacts` (`company_id` taken from the path)

| Param | Description |
|-------|-------------|
| `search` | name, email, phone, job_title; also company.name |
| `company_id` | Exact FK |
| `is_primary` | Boolean |
| `sort` | `id`, `name`, `email`, `job_title`, `is_primary`, `company_id`, `created_at`, `updated_at` |

Fields: `company_id`, `name`, `email`, `phone`, `job_title`, `is_primary`.

### Companies index

| Param | Description |
|-------|-------------|
| `search` | name, email, city, kvk_number, vat_number, phone, notes |
| `city` | Partial, case-insensitive match on city |
| `country` | Exact country code/name |
| `sort` | `id`, `name`, `city`, `country`, `email`, `created_at`, `updated_at` |

### Contracts index (Filament parity + agent extras)

Portal/Stripe fields such as `auto_collect` and Stripe IDs may appear on contract resources; agents must use the API and should not scrape `/portal`.

| Param | Description |
|-------|-------------|
| `search` | name, reference, notes; also company.name, product.name/sku, vendor.name |
| `company_id` | Exact FK |
| `product_id` | Exact FK |
| `vendor_id` | Exact FK |
| `purchase_bundle_id` | Exact FK |
| `status` | `active`, `pending`, `cancelled`, `expired` |
| `type` | `license`, `support`, `subscription`, `service`, `other` |
| `billing_cycle` | `monthly`, `quarterly`, `yearly`, `once` |
| `auto_renew` | Boolean (`true`/`false`/`1`/`0`) |
| `sale_min` / `sale_max` | Unit `sale_price` range (Filament “Sale”) |
| `cost_min` / `cost_max` | Unit `cost_price` range |
| `margin_min` / `margin_max` | Approx. margin: `qty×sale − qty×cost` (ignores purchase-bundle allocation) |
| `starts_after` / `starts_before` | `start_date` (inclusive, `YYYY-MM-DD`) |
| `renews_after` / `renews_before` | `renewal_date` |
| `cancels_after` / `cancels_before` | `cancelled_at` |
| `invoices_after` / `invoices_before` | `next_invoice_date` |
| `sort` | `id`, `name`, `reference`, `status`, `type`, `billing_cycle`, `sale_price`, `cost_price`, `quantity`, `start_date`, `renewal_date`, `cancelled_at`, `next_invoice_date`, `company_id`, `product_id`, `vendor_id`, `auto_renew`, `created_at`, `updated_at` |

Default sort when `sort` is omitted: `id` descending.

### Upcoming renewals (`GET /api/v1/contracts/upcoming-renewals`)

| Param | Description |
|-------|-------------|
| `days` | Horizon (default 30, max 365). Active contracts with `renewal_date` in `[today, today+days]` |
| `per_page` / `page` | Standard pagination |

Sorted by `renewal_date` ascending. Returns the same `Contract` resource shape as the contracts index.

### Renewal reminder notifications (console only)

There is **no** HTTP trigger for `contracts:send-renewal-reminders`. The artisan command emails/notifies every admin/manager for matching contracts on **every** run (not idempotent / not safe to spam from agents).

```bash
php artisan contracts:send-renewal-reminders --days=30
```

Agents should list renewals via the dashboard or `upcoming-renewals` endpoint and act in the product UI / CRM — do not invoke the reminder command from automation unless an operator explicitly schedules it (cron).

### Products index

| Param | Description |
|-------|-------------|
| `search` | name, sku, description; also vendor.name |
| `vendor_id` | Exact FK |
| `type` | Product type enum |
| `billing_cycle` | Billing cycle enum |
| `active` | Boolean |
| `sale_min` / `sale_max` | `default_sale_price` range |
| `cost_min` / `cost_max` | `default_cost_price` range |
| `sort` | `id`, `name`, `sku`, `type`, `vendor_id`, `active`, `default_cost_price`, `default_sale_price`, `billing_cycle`, `created_at`, `updated_at` |

### Product components (composition / BOM)

Bill of materials: a composite catalog product (`product_id`) contains other catalog products (`component_id`) with a `quantity`. Effective package cost is the recursive sum of component costs × quantity (`Product::computeEffectiveCost`).

Top-level CRUD: `/api/v1/product-components`

Nested convenience (same RBAC):

- `GET /api/v1/products/{product}/components`
- `POST /api/v1/products/{product}/components` (`product_id` taken from the path)

| Param | Description |
|-------|-------------|
| `product_id` | Exact FK (the package) |
| `component_id` | Exact FK (the part) |
| `sort` | `id`, `product_id`, `component_id`, `quantity`, `created_at`, `updated_at` |

Fields: `product_id`, `component_id`, `quantity` (default 1, min 1). A product cannot be a component of itself; `(product_id, component_id)` is unique.

### Purchase bundles

Resell / shared-purchase packages with a fixed `total_cost` allocated evenly across linked **active** contracts (`allocation_method=even`). Contracts reference a bundle via `purchase_bundle_id`.

Top-level CRUD: `/api/v1/purchase-bundles`

Show/index include computed metrics: `annual_cost`, `active_contracts_count`, `allocated_annual_cost_per_contract`, `annual_resale`, `recovery_percentage`.

| Param | Description |
|-------|-------------|
| `search` | name, reference, notes; also vendor.name |
| `vendor_id` | Exact FK |
| `billing_cycle` | `monthly`, `quarterly`, `yearly`, `once` |
| `allocation_method` | Currently only `even` |
| `currency` | Exact (e.g. `EUR`) |
| `cost_min` / `cost_max` | `total_cost` range |
| `starts_after` / `starts_before` | `start_date` |
| `renews_after` / `renews_before` | `renewal_date` |
| `sort` | `id`, `name`, `reference`, `vendor_id`, `total_cost`, `currency`, `billing_cycle`, `allocation_method`, `start_date`, `renewal_date`, `created_at`, `updated_at` |

Fields: `vendor_id`, `name`, `reference`, `total_cost`, `currency`, `billing_cycle`, `allocation_method`, `start_date`, `renewal_date`, `notes`.

### Vendors index

| Param | Description |
|-------|-------------|
| `search` | name, email, website, phone, notes |
| `sort` | `id`, `name`, `email`, `website`, `created_at`, `updated_at` |

### API tokens (own only)

Authenticated users manage **their own** Sanctum personal access tokens:

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/api/v1/tokens` | List own tokens: `id`, `name`, `abilities`, `last_used_at`, `created_at` — **never** plaintext |
| `POST` | `/api/v1/tokens` | Create: body `{ "name": "...", "abilities": ["*"] }` (abilities optional, default `["*"]`). Response includes `plain_text_token` **once** |
| `DELETE` | `/api/v1/tokens/{id}` | Revoke own token by id (`404` if not owned) |

There is no endpoint to list or revoke another user's tokens (scope kept small). MCP tools for tokens are a follow-up; use REST for now.

### License keys

`contracts.license_keys` are **encrypted at rest**. Audit logging redacts them. The API:

- omits `license_keys` from index listings
- returns decrypted values on show/create/update only for sales+ roles

## Example requests

```bash
export APP_URL=http://localhost
export TOKEN=your-plain-text-token

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/dashboard"

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/contracts/upcoming-renewals?days=30"

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/contacts?company_id=12&is_primary=true"

curl -sS -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"name":"Ada Lovelace","email":"ada@example.com","job_title":"CTO","is_primary":true}' \\
  "$APP_URL/api/v1/companies/12/contacts"

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/companies?search=acme&sort=name&order=asc"

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/contracts?status=active&billing_cycle=yearly&auto_renew=true&sale_min=100&renews_before=2026-12-31&sort=renewal_date&order=asc"

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/contracts?company_id=12&type=license&margin_min=50&per_page=50"

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/products?vendor_id=3&active=1&sort=default_sale_price&order=desc"

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/products/5/components"

curl -sS -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"component_id":9,"quantity":2}' \\
  "$APP_URL/api/v1/products/5/components"

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/purchase-bundles?vendor_id=3&cost_min=100&sort=name"

curl -sS -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"name":"Hosting pool","vendor_id":3,"total_cost":1200,"billing_cycle":"yearly"}' \\
  "$APP_URL/api/v1/purchase-bundles"

curl -sS -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"name":"Acme BV","email":"info@acme.example","country":"NL"}' \\
  "$APP_URL/api/v1/companies"

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/contracts/42"

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/tokens"

curl -sS -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"name":"ci-agent"}' \\
  "$APP_URL/api/v1/tokens"
```

## Errors

- `401` — missing/invalid token
- `403` — authenticated but role cannot perform the action
- `422` — validation errors
- `404` — unknown id

Create/update/delete still flow through the existing `Auditable` model trait (contacts and product components are not auditable; purchase bundles are).

## MCP sidecar (Cursor / agents)

An optional Node MCP server under [`mcp/`](mcp/) exposes the same Agent API as tools (stdio transport). Laravel is unchanged; the sidecar calls `{MSP_API_BASE}/api/v1` with `MSP_API_TOKEN`.

### Quick start

```bash
cd mcp
cp .env.example .env   # set MSP_API_BASE + MSP_API_TOKEN
npm install
npm run build
npm start              # or: npm run dev (tsx)
```

### Cursor config example

```json
{
  "mcpServers": {
    "msp-platform": {
      "command": "node",
      "args": ["/absolute/path/to/msp-platform/mcp/dist/index.js"],
      "env": {
        "MSP_API_BASE": "http://localhost",
        "MSP_API_TOKEN": "your-plain-text-sanctum-token"
      }
    }
  }
}
```

Token self-service is **REST-only** for now (MCP follow-up).

Full tool list, smoke script, and notes: [`mcp/README.md`](mcp/README.md).
