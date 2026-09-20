import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

const bundleFields = {
  vendor_id: z.number().int().optional().nullable(),
  name: z.string().optional().describe("Bundle name (required on create)"),
  reference: z.string().optional().nullable(),
  total_cost: z.number().optional().nullable(),
  currency: z.string().optional().nullable(),
  billing_cycle: z.enum(["monthly", "quarterly", "yearly", "once"]).optional().nullable(),
  allocation_method: z.enum(["even"]).optional().nullable(),
  start_date: z.string().optional().nullable().describe("YYYY-MM-DD"),
  renewal_date: z.string().optional().nullable().describe("YYYY-MM-DD"),
  notes: z.string().optional().nullable(),
};

export function registerPurchaseBundles(server: McpServer, client: MspClient): void {
  server.tool(
    "list_purchase_bundles",
    "List/search purchase (resell) bundles. Filter by vendor_id, billing_cycle, cost range, dates.",
    {
      ...pagination,
      vendor_id: z.number().int().optional(),
      billing_cycle: z.enum(["monthly", "quarterly", "yearly", "once"]).optional(),
      allocation_method: z.enum(["even"]).optional(),
      currency: z.string().optional(),
      cost_min: z.number().optional(),
      cost_max: z.number().optional(),
      starts_after: z.string().optional(),
      starts_before: z.string().optional(),
      renews_after: z.string().optional(),
      renews_before: z.string().optional(),
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/purchase-bundles", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "get_purchase_bundle",
    "Show a purchase bundle by id (includes allocation/recovery metrics).",
    { id: z.number().int().describe("Purchase bundle id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.get(`/purchase-bundles/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_purchase_bundle",
    "Create a purchase bundle (sales+).",
    {
      name: z.string().describe("Bundle name"),
      vendor_id: bundleFields.vendor_id,
      reference: bundleFields.reference,
      total_cost: bundleFields.total_cost,
      currency: bundleFields.currency,
      billing_cycle: bundleFields.billing_cycle,
      allocation_method: bundleFields.allocation_method,
      start_date: bundleFields.start_date,
      renewal_date: bundleFields.renewal_date,
      notes: bundleFields.notes,
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/purchase-bundles", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "update_purchase_bundle",
    "Partial-update a purchase bundle (sales+).",
    { id: z.number().int().describe("Purchase bundle id"), ...bundleFields },
    async ({ id, ...fields }) => {
      try {
        return jsonResult(await client.patch(`/purchase-bundles/${id}`, compactBody(fields)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "delete_purchase_bundle",
    "Delete a purchase bundle (admin/manager).",
    { id: z.number().int().describe("Purchase bundle id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.delete(`/purchase-bundles/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
