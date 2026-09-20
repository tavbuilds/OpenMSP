#!/usr/bin/env node
/**
 * MSP Platform — MCP sidecar for the Agent API.
 *
 * Env:
 *   MSP_API_BASE  — Laravel APP_URL, e.g. http://localhost (requests hit {base}/api/v1)
 *   MSP_API_TOKEN — Sanctum personal access token (create from admin → API-tokens)
 *
 * Transport: stdio (Cursor / Claude Desktop / Grok Bot style hosts).
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { MspClient } from "./client.js";
import { registerCompanies } from "./tools/companies.js";
import { registerContacts } from "./tools/contacts.js";
import { registerContracts } from "./tools/contracts.js";
import { registerDashboard } from "./tools/dashboard.js";
import { registerProductComponents } from "./tools/product-components.js";
import { registerProducts } from "./tools/products.js";
import { registerPurchaseBundles } from "./tools/purchase-bundles.js";
import { registerVendors } from "./tools/vendors.js";

function createServer(client: MspClient): McpServer {
  const server = new McpServer({
    name: "msp-platform",
    version: "1.1.0",
  });

  registerDashboard(server, client);
  registerCompanies(server, client);
  registerContacts(server, client);
  registerContracts(server, client);
  registerProducts(server, client);
  registerProductComponents(server, client);
  registerPurchaseBundles(server, client);
  registerVendors(server, client);

  return server;
}

async function main() {
  const client = MspClient.fromEnv();
  const server = createServer(client);
  const transport = new StdioServerTransport();
  await server.connect(transport);
  // stdout is reserved for JSON-RPC; log to stderr only
  console.error(`msp-platform MCP server ready → ${client.baseUrl}/api/v1`);
}

main().catch((err) => {
  console.error(err instanceof Error ? err.message : err);
  process.exit(1);
});
