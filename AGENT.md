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
| Planned tasks | `/api/v1/planned-tasks` |
| Upcoming planned tasks | `GET /api/v1/planned-tasks/upcoming` |
| Domains | `/api/v1/domains` |
| Upcoming domain expiries | `GET /api/v1/domains/upcoming-expiries` |
| Assign domains | `POST /api/v1/domains/assign` |
| Endpoints | `/api/v1/endpoints` |
| Upcoming endpoint expiries | `GET /api/v1/endpoints/upcoming` |
| Rotate endpoint webhook | `POST /api/v1/endpoints/{id}/rotate-webhook` |
| Uptime Kuma | `/api/v1/integrations/uptime-kuma` |
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

Read-only portfolio metrics. Formulas match Filament `PortfolioStats` / `UpcomingRenewals`.

> **Renewal watching covers committed terms only.** Everything below that talks
> about renewals or notice deadlines counts `billing_cycle` `quarterly` and
> `yearly` and skips `monthly` and `once`: a monthly contract can be cancelled
> at any month boundary, so its renewal is not a deadline, and one-time work
> never renews. Revenue metrics (`mrr`, `arr`, `annual_margin`,
> `active_contracts_count`) are unaffected and still include every active
> contract. See `BillingCycle::withRenewalTerm()` and the
> `Contract::withUpcomingRenewalTerm()` scope.


| Field | Meaning |
|-------|---------|
| `active_contracts_count` | Contracts with `status=active` |
| `arr` | Sum of `Contract::annual_revenue` over active contracts |
| `mrr` | `arr / 12` |
| `annual_margin` | Sum of `Contract::annual_margin` over active contracts |
| `upcoming_renewals_30d_count` | Active term contracts with `renewal_date` in the next 30 days |
| `upcoming_renewals` | Short list (≤25) of active term renewals in the next **60** days (widget horizon) |
| `upcoming_notice_deadlines` | Active term contracts whose computed `notice_deadline` falls within 60 days |
| `upcoming_planned_tasks` | Open planned work (moves, migrations, on-site) due within 60 days, including overdue |
| `domains_expiring_60d_count` | Domains still at the registrar whose actionable date is within 60 days or already past |
| `upcoming_domain_expiries` | Short list (≤25) of those domains |
| `endpoints_expiring_60d_count` | Endpoints with an expiry date within 60 days or already past |
| `upcoming_endpoint_expiries` | Short list (≤25) of those endpoints |

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
| `days` | Horizon (default 30, max 365). Active term contracts with `renewal_date` in `[today, today+days]` |
| `per_page` / `page` | Standard pagination |

Sorted by `renewal_date` ascending. Returns the same `Contract` resource shape as the contracts index.

### Planned tasks index (`/api/v1/planned-tasks`)

Moves, migrations, on-site jobs, and other dated work.

| Param | Description |
|-------|-------------|
| `search` | title, locations, notes; also company.name |
| `company_id` | Exact customer FK |
| `assigned_user_id` | Exact assignee FK |
| `kind` | `relocation` \| `migration` \| `onsite` \| `project` \| `other` |
| `status` | `planned` \| `in_progress` \| `blocked` \| `done` \| `cancelled` |
| `priority` | `low` \| `normal` \| `high` \| `urgent` |
| `due_after` / `due_before` | `due_on` date range |
| `open` | Boolean; exclude done/cancelled |
| `overdue` | Boolean; open tasks with `due_on` before today |
| `sort` | `id`, `title`, `due_on`, `kind`, `status`, `priority`, `created_at`, `updated_at` |

`GET /api/v1/planned-tasks/upcoming?days=60` returns open tasks due within the horizon (overdue included). Default sort: `due_on` ascending.

### Domains

A domain is not a contract. See [docs/DOMAINS.md](docs/DOMAINS.md). The list defaults to `lifecycle=present` (still at the registrar). Pass `lifecycle=removed` or `lifecycle=all` for the rest.

| Param | Description |
|-------|-------------|
| `search` | name, extension, notes, registrar status; also company.name |
| `company_id` | Exact customer FK |
| `unassigned` | Boolean; `company_id` is null |
| `source` | `manual` \| `openprovider` |
| `extension` | Exact, e.g. `nl` |
| `auto_renew` | Effective boolean |
| `expired` | Boolean, on the actionable date (`renewal_date`, else `expires_at`) |
| `expires_within` | Actionable date within N days, including already expired |
| `lifecycle` | `present` (default) \| `removed` \| `all` |
| `sort` | `id`, `name`, `extension`, `expires_at`, `renewal_date`, `status`, `auto_renew`, `company_id`, `source`, `removed_at`, `created_at`, `updated_at` |

Default sort is the actionable date, soonest first. `GET /api/v1/domains/upcoming-expiries?days=60` is the dashboard horizon, present domains only.

Create is for a domain typed in by hand (`source` is forced to `manual`). On an Openprovider domain, `name`, `extension`, `expires_at`, `renewal_date`, `auto_renew`, `status` and `product_id` are rejected when the value would change — the next sync would overwrite them. `company_id`, `notes`, `notes_visible_to_customer` and the `notify_*` toggles are yours.

`POST /api/v1/domains/assign` body `{ "company_id": 1, "ids": [2, 3] }` (max 100). Sales+.

Moving `expires_at` or `renewal_date` on a manual domain clears the reminder cycle.

### Endpoints

Certificate and other expiry monitors. See [docs/ENDPOINTS.md](docs/ENDPOINTS.md).

| Param | Description |
|-------|-------------|
| `search` | name, hostname, url, notes; also company.name |
| `company_id` | Exact customer FK |
| `kind` | `certificate` \| `domain` \| `other` |
| `source` | `manual` \| `webhook` \| `uptime_kuma` |
| `status` | `ok` \| `warning` \| `expired` \| `unknown` (`last_status`) |
| `expired` | Boolean, on `expires_at` |
| `expires_within` | `expires_at` within N days, including already expired |
| `sort` | `id`, `name`, `kind`, `hostname`, `expires_at`, `source`, `last_status`, `company_id`, `created_at`, `updated_at` |

`webhook_url` (the per-endpoint Uptime Kuma URL) is returned on show, create, update and rotate. It is omitted from lists because it contains the token. `POST /api/v1/endpoints/{id}/rotate-webhook` (sales+) replaces it; the old URL 404s.

A changed `expires_at` restarts the reminder cycle.

`DELETE /api/v1/endpoints/{id}` removes the endpoint. When a Uptime Kuma dashboard login is stored, OpenMSP also deletes the matching monitor and the OpenMSP webhook. Two monitors for that address, or a Kuma error, leave the endpoint in place (HTTP 422). Without that login the endpoint is still deleted.

### Uptime Kuma

The API key from **Settings → Security → API Keys** authenticates `GET /metrics` (HTTP basic auth, the key is the password, the username is ignored). It does **not** create monitors or notifications.

Adding webhook URLs uses the Kuma dashboard username and password over Socket.IO, the same interface as the Kuma UI. Store both under **Catalog → Uptime Kuma**, or with `PUT /api/v1/integrations/uptime-kuma` (`base_url`, `api_key`, `username`, `password`). The key and the password are encrypted and are never returned.

`POST /api/v1/integrations/uptime-kuma/webhooks` (admin/manager, optional `endpoint_id`) creates a JSON webhook notification per endpoint and attaches it to the monitor with the same URL or hostname. When no monitor matches, OpenMSP creates an HTTPS monitor for the endpoint URL, hostname, or a domain-like name. Certificate expiry and domain expiry notifications on that monitor are turned off, because OpenMSP sends those reminders. Other notification channels already on the monitor are left as they are. Two monitors for the same address are skipped. The response counts `monitors_created`. An endpoint with no URL, hostname or domain-like name stays in `unmatched`. A socket error on one endpoint is retried up to 3 times, then listed in `failed`, stored on the endpoint, and the push continues with the rest. `failed_only: true` sends only the endpoints that still failed. `endpoint_ids` sends only that selection, in one batch. The same request then reads `monitor_cert_days_remaining` and writes the expiry onto those endpoints. The nightly job still refreshes that date.

| Method | Path | Who | Notes |
|--------|------|-----|-------|
| `GET` | `/api/v1/integrations/uptime-kuma` | any role | `base_url`, `api_key_set`, last sync. Never the key |
| `PUT` / `PATCH` | `/api/v1/integrations/uptime-kuma` | admin/manager | `{ "base_url", "api_key", "username", "password" }`. Empty string clears that field. A trailing `/metrics` is stripped. The key and password are write-only |
| `POST` | `/api/v1/integrations/uptime-kuma/test` | admin/manager | Scrapes `/metrics`, returns `cert_monitors` |
| `POST` | `/api/v1/integrations/uptime-kuma/sync` | admin/manager | `{ "create_missing": false }`. Matches on URL, then hostname, and writes `expires_at` |
| `POST` | `/api/v1/integrations/uptime-kuma/webhooks` | admin/manager | `{ "endpoint_id": 4 }` or `{ "endpoint_ids": [4, 5] }` optional. Adds webhook URLs and creates missing HTTPS monitors in Kuma |

`create_missing: true` adds an endpoint for each certificate monitor that matches nothing. The nightly `uptime-kuma:sync` (06:50) updates matches and does not create. The key is encrypted at rest with the other platform secrets.

### Renewal reminder notifications (console only)

There is **no** HTTP trigger for `contracts:send-renewal-reminders`. The artisan command emails/notifies every admin/manager for matching contracts on **every** run (not idempotent / not safe to spam from agents). It follows the same term rule as the dashboard: monthly and one-time contracts never trigger a reminder.

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

curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  "$APP_URL/api/v1/domains?unassigned=1&lifecycle=present"

curl -sS -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"name":"vpn.example.nl","kind":"certificate","hostname":"vpn.example.nl"}' \\
  "$APP_URL/api/v1/endpoints"

curl -sS -X PUT -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"base_url":"https://status.example","api_key":"uk2_…"}' \\
  "$APP_URL/api/v1/integrations/uptime-kuma"
```

## Errors

- `401` — missing/invalid token
- `403` — authenticated but role cannot perform the action
- `422` — validation errors
- `404` — unknown id

Create/update/delete still flow through the existing `Auditable` model trait (contacts and product components are not auditable; purchase bundles are).

## MCP (Grok, Cursor, and other agents)

The app **hosts MCP itself** at:

```
{APP_URL}/mcp
```

Streamable HTTP, Sanctum Bearer token (same tokens as `/api/v1`). No Node
install. In admin: **System → MCP** copies the URL; **System → API tokens**
creates the token.

Grok: [grok.com/connectors](https://grok.com/connectors) → New Connector →
Custom → paste `{APP_URL}/mcp` → Bearer + token.

```bash
curl -sS -X POST -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}' \
  "$APP_URL/mcp"
```

Tools match the Agent API (dashboard, companies, contacts, contracts, catalog,
BOM, purchase bundles, vendors, planned tasks, domains, endpoints, Uptime Kuma).
RBAC is identical. Token self-service stays REST-only (`/api/v1/tokens`).
`set_uptime_kuma`, `test_uptime_kuma`, `sync_uptime_kuma` and `push_uptime_kuma_webhooks` are admin/manager.
The API key is write-only.

An optional Node **stdio** sidecar under [`mcp/`](mcp/) still exists for local
Cursor/CLI hosts that cannot call HTTP. Prefer `{APP_URL}/mcp` for Grok and any
remote agent.

### Cursor stdio sidecar (optional)

```bash
cd mcp
cp .env.example .env   # set MSP_API_BASE + MSP_API_TOKEN
npm install
npm run build
```

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

Full sidecar notes: [`mcp/README.md`](mcp/README.md).
