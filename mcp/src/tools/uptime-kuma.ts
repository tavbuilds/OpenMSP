import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";

export function registerUptimeKuma(server: McpServer, client: MspClient): void {
  server.tool(
    "get_uptime_kuma",
    "Whether a Uptime Kuma base URL, API key and dashboard login are stored. The key and password are never returned.",
    {},
    async () => {
      try {
        return jsonResult(await client.get("/integrations/uptime-kuma"));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "set_uptime_kuma",
    "Store the Uptime Kuma base URL, API key and dashboard login (admin/manager). The key and password are encrypted and are not echoed back. An empty string clears a value. The API key only unlocks GET /metrics. The username and password add webhook URLs.",
    {
      base_url: z
        .string()
        .optional()
        .describe("https://status.example.com — without /metrics. A trailing /metrics is stripped"),
      api_key: z
        .string()
        .optional()
        .describe("Key from Uptime Kuma → Settings → Security → API Keys. Empty string clears it"),
      username: z.string().optional().describe("Dashboard login. Empty string clears it"),
      password: z.string().optional().describe("Dashboard password. Empty string clears it"),
    },
    async (args) => {
      try {
        return jsonResult(await client.put("/integrations/uptime-kuma", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "test_uptime_kuma",
    "GET /metrics with the stored API key (admin/manager).",
    {},
    async () => {
      try {
        return jsonResult(await client.post("/integrations/uptime-kuma/test", {}));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "push_uptime_kuma_webhooks",
    "Create each endpoint webhook in Uptime Kuma and attach it (admin/manager). Creates the HTTPS monitor when none matches. Tries a failure up to 3 times. failed_only resubmits domains that still failed. endpoint_ids sends only that selection. Requires the dashboard login.",
    {
      endpoint_id: z.number().int().optional().describe("Only this endpoint. Omit for every endpoint"),
      endpoint_ids: z
        .array(z.number().int())
        .optional()
        .describe("Only these endpoints. Omit for every endpoint"),
      failed_only: z.boolean().optional().describe("Only domains whose last push failed. Default false"),
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/integrations/uptime-kuma/webhooks", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "sync_uptime_kuma",
    "Import certificate expiry from Uptime Kuma /metrics into endpoints (admin/manager). create_missing adds an endpoint per unmatched certificate monitor. Does not turn Kuma notifications on.",
    {
      create_missing: z
        .boolean()
        .optional()
        .describe("Create endpoints for certificate monitors that do not match yet. Default false"),
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/integrations/uptime-kuma/sync", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
