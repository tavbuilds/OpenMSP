import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

const kind = z.enum(["certificate", "domain", "other"]).optional();
const source = z.enum(["manual", "webhook", "uptime_kuma"]).optional();

const fields = {
  name: z.string().optional().describe("Name (required on create)"),
  company_id: z.number().int().optional().nullable().describe("Customer id. Omit for an internal endpoint"),
  kind,
  hostname: z.string().optional().nullable(),
  url: z.string().optional().nullable().describe("https://…"),
  expires_at: z.string().optional().nullable().describe("YYYY-MM-DD"),
  notes: z.string().optional().nullable(),
  notify_30: z.boolean().optional(),
  notify_14: z.boolean().optional(),
  notify_7: z.boolean().optional(),
  notify_1: z.boolean().optional(),
  notify_expired: z.boolean().optional(),
  notify_customer: z.boolean().optional().describe("Also email the customer contacts"),
};

export function registerEndpoints(server: McpServer, client: MspClient): void {
  server.tool(
    "list_endpoints",
    "List certificate and endpoint monitors. webhook_url is omitted; get_endpoint returns it.",
    {
      ...pagination,
      company_id: z.number().int().optional(),
      kind,
      source,
      status: z.enum(["ok", "warning", "expired", "unknown"]).optional(),
      expired: z.boolean().optional(),
      expires_within: z.number().int().min(0).max(3650).optional(),
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/endpoints", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "list_upcoming_endpoints",
    "Endpoints with an expiry inside N days, including already expired.",
    {
      days: z.number().int().min(1).max(365).optional().describe("Horizon, default 60"),
      per_page: pagination.per_page,
      page: pagination.page,
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/endpoints/upcoming", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "get_endpoint",
    "Show an endpoint, including webhook_url. push_uptime_kuma_webhooks creates the Uptime Kuma HTTPS monitor when it is missing and attaches that URL.",
    { id: z.number().int().describe("Endpoint id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.get(`/endpoints/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_endpoint",
    "Add an endpoint (sales+). push_uptime_kuma_webhooks then creates the Uptime Kuma HTTPS monitor when it is missing, attaches the webhook and turns certificate and domain expiry notifications off.",
    {
      name: z.string().describe("Name, e.g. VPN certificate"),
      company_id: fields.company_id,
      kind,
      hostname: fields.hostname,
      url: fields.url,
      expires_at: fields.expires_at,
      notes: fields.notes,
      notify_customer: fields.notify_customer,
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/endpoints", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "update_endpoint",
    "Partial-update an endpoint (sales+). A new expiry date restarts reminders. Returns webhook_url.",
    { id: z.number().int().describe("Endpoint id"), ...fields },
    async ({ id, ...rest }) => {
      try {
        return jsonResult(await client.patch(`/endpoints/${id}`, compactBody(rest)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "rotate_endpoint_webhook",
    "Issue a new webhook URL (sales+). The previous URL stops working.",
    { id: z.number().int().describe("Endpoint id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.post(`/endpoints/${id}/rotate-webhook`, {}));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "delete_endpoint",
    "Delete an endpoint (admin/manager). Also removes the matching Uptime Kuma monitor and the OpenMSP webhook. Two matches or a Kuma error leave the endpoint in place.",
    { id: z.number().int().describe("Endpoint id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.delete(`/endpoints/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
