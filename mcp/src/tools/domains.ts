import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

const source = z.enum(["manual", "openprovider"]).optional();

const fields = {
  name: z.string().optional().describe("Domain name (required on create). Stored lowercased"),
  extension: z.string().optional().nullable(),
  company_id: z.number().int().optional().nullable().describe("Customer id"),
  product_id: z.number().int().optional().nullable(),
  expires_at: z.string().optional().nullable().describe("Registry expiry YYYY-MM-DD"),
  renewal_date: z.string().optional().nullable().describe("Actionable date YYYY-MM-DD"),
  auto_renew: z.boolean().optional(),
  status: z.string().optional().nullable().describe("Registrar status, for a hand-entered domain"),
  notes: z.string().optional().nullable(),
  notes_visible_to_customer: z.boolean().optional(),
  notify_30: z.boolean().optional(),
  notify_14: z.boolean().optional(),
  notify_7: z.boolean().optional(),
  notify_1: z.boolean().optional(),
  notify_expired: z.boolean().optional(),
};

export function registerDomains(server: McpServer, client: MspClient): void {
  server.tool(
    "list_domains",
    "List/search domains. Default lifecycle=present hides domains the registrar no longer returns.",
    {
      ...pagination,
      company_id: z.number().int().optional(),
      source,
      extension: z.string().optional(),
      auto_renew: z.boolean().optional(),
      unassigned: z.boolean().optional().describe("Only domains with no customer"),
      expired: z.boolean().optional(),
      expires_within: z.number().int().min(0).max(3650).optional(),
      lifecycle: z.enum(["present", "removed", "all"]).optional().describe("Default present"),
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/domains", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "list_upcoming_domain_expiries",
    "Domains still at the registrar whose renewal or expiry falls within N days, including already expired.",
    {
      days: z.number().int().min(1).max(365).optional().describe("Horizon, default 60"),
      per_page: pagination.per_page,
      page: pagination.page,
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/domains/upcoming-expiries", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "get_domain",
    "Show a domain by id.",
    { id: z.number().int().describe("Domain id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.get(`/domains/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_domain",
    "Create a domain typed in by hand (sales+). Openprovider domains arrive via sync.",
    {
      name: z.string().describe("Domain name"),
      extension: fields.extension,
      company_id: fields.company_id,
      product_id: fields.product_id,
      expires_at: fields.expires_at,
      renewal_date: fields.renewal_date,
      auto_renew: fields.auto_renew,
      status: fields.status,
      notes: fields.notes,
      notes_visible_to_customer: fields.notes_visible_to_customer,
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/domains", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "update_domain",
    "Partial-update a domain (sales+). On an Openprovider domain only the customer, notes and reminders stick.",
    { id: z.number().int().describe("Domain id"), ...fields },
    async ({ id, ...rest }) => {
      try {
        return jsonResult(await client.patch(`/domains/${id}`, compactBody(rest)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "assign_domains",
    "Assign many domains to one customer (sales+). Use this after an Openprovider import.",
    {
      company_id: z.number().int().describe("Customer id"),
      ids: z.array(z.number().int()).min(1).max(100).describe("Domain ids"),
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/domains/assign", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "delete_domain",
    "Delete a domain (admin/manager).",
    { id: z.number().int().describe("Domain id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.delete(`/domains/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
