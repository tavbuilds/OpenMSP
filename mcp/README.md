# MSP Platform MCP sidecar

Node MCP server that wraps the Laravel Agent API (`{MSP_API_BASE}/api/v1`) so Cursor / Grok Bot-style agents can call companies, contacts, contracts, products, product components, purchase bundles, vendors, dashboard, and upcoming renewals as tools — without hand-rolling HTTP.

Laravel stays untouched; this package lives under `mcp/` and talks over Sanctum Bearer tokens.

## Requirements

- Node.js 18+
- Running MSP app with Agent API + a Sanctum PAT (see root [`AGENT.md`](../AGENT.md))

## Setup

```bash
cd mcp
cp .env.example .env   # edit MSP_API_BASE + MSP_API_TOKEN
npm install
npm run build
```

Or run TypeScript directly:

```bash
npm run dev
```

Required env vars (also accepted from the host MCP config `env` block):

| Variable         | Example             | Meaning                                      |
|------------------|---------------------|----------------------------------------------|
| `MSP_API_BASE`   | `http://localhost`  | Laravel `APP_URL` (no trailing slash)        |
| `MSP_API_TOKEN`  | `1|...`            | Sanctum personal access token (Bearer)       |

Do **not** commit `.env` or real tokens.

## Cursor MCP config

Add to Cursor MCP settings (path absolute to your clone):

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

Dev alternative (no build step):

```json
{
  "mcpServers": {
    "msp-platform": {
      "command": "npx",
      "args": ["tsx", "/absolute/path/to/msp-platform/mcp/src/index.ts"],
      "env": {
        "MSP_API_BASE": "http://localhost",
        "MSP_API_TOKEN": "your-plain-text-sanctum-token"
      }
    }
  }
}
```

## Tools

| Tool | HTTP |
|------|------|
| `get_dashboard` | `GET /api/v1/dashboard` |
| `list_upcoming_renewals` | `GET /api/v1/contracts/upcoming-renewals` |
| `list_companies` / `get_company` / `create_company` / `update_company` / `delete_company` | `/api/v1/companies` |
| `list_contacts` / `get_contact` / `create_contact` / `update_contact` / `delete_contact` | `/api/v1/contacts` |
| `list_company_contacts` / `create_company_contact` | `/api/v1/companies/{id}/contacts` |
| `list_contracts` / `get_contract` / `create_contract` / `update_contract` / `delete_contract` | `/api/v1/contracts` |
| `list_products` / `get_product` / `create_product` / `update_product` / `delete_product` | `/api/v1/products` |
| `list_product_components` / `get_product_component` / `create_product_component` / `update_product_component` / `delete_product_component` | `/api/v1/product-components` |
| `list_product_bom` / `create_product_bom_item` | `/api/v1/products/{id}/components` |
| `list_purchase_bundles` / `get_purchase_bundle` / `create_purchase_bundle` / `update_purchase_bundle` / `delete_purchase_bundle` | `/api/v1/purchase-bundles` |
| `list_vendors` / `get_vendor` / `create_vendor` / `update_vendor` / `delete_vendor` | `/api/v1/vendors` |

List tools accept the same filter query params as the REST API (see `AGENT.md`). Writes use PATCH for updates. RBAC matches the API (viewer read; sales+ write; admin/manager delete).

Token self-service (`/api/v1/tokens`) is **REST-only** for now (MCP follow-up).

## Smoke test (curl parity)

With the API up and env set:

```bash
export MSP_API_BASE=http://localhost
export MSP_API_TOKEN=your-token
npm run smoke
```

`scripts/smoke.sh` hits dashboard, companies, and upcoming-renewals the same way the MCP client does.

## Notes

- stdout is reserved for MCP JSON-RPC; diagnostics go to stderr.
- Renewal reminder emails stay console-only (`contracts:send-renewal-reminders`) — not exposed as a tool.
- Keep OpenAPI / `AGENT.md` as the source of truth when the Laravel API changes; update tool schemas here to match.
