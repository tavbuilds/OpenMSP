import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

const contractFields = {
  company_id: z.number().int().optional().describe("Company FK (required on create)"),
  product_id: z.number().int().optional().nullable(),
  vendor_id: z.number().int().optional().nullable(),
  purchase_bundle_id: z.number().int().optional().nullable(),
  name: z.string().optional().describe("Contract name (required on create)"),
  reference: z.string().optional().nullable(),
  type: z.enum(["license", "support", "subscription", "service", "other"]).optional().nullable(),
  quantity: z.number().int().min(1).optional().nullable(),
  cost_price: z.number().min(0).optional().nullable(),
  sale_price: z.number().min(0).optional().nullable(),
  currency: z.string().length(3).optional().nullable(),
  billing_cycle: z.enum(["monthly", "quarterly", "yearly", "once"]).optional().nullable(),
  start_date: z.string().optional().describe("YYYY-MM-DD (required on create)"),
  renewal_date: z.string().optional().nullable().describe("YYYY-MM-DD"),
  notice_period_days: z.number().int().min(0).optional().nullable(),
  auto_renew: z.boolean().optional().nullable(),
  status: z.enum(["active", "pending", "cancelled", "expired"]).optional().nullable(),
  next_invoice_date: z.string().optional().nullable().describe("YYYY-MM-DD"),
  cancelled_at: z.string().optional().nullable().describe("YYYY-MM-DD"),
  license_keys: z.string().optional().nullable().describe("Encrypted at rest; sales+ only"),
  notes: z.string().optional().nullable(),
};

export function registerContracts(server: McpServer, client: MspClient): void {
  server.tool(
    "list_contracts",
    "List/search contracts with Filament-parity filters (status, type, billing, price ranges, date ranges, FKs).",
    {
      ...pagination,
      company_id: z.number().int().optional(),
      product_id: z.number().int().optional(),
      vendor_id: z.number().int().optional(),
      purchase_bundle_id: z.number().int().optional(),
      status: z.enum(["active", "pending", "cancelled", "expired"]).optional(),
      type: z.enum(["license", "support", "subscription", "service", "other"]).optional(),
      billing_cycle: z.enum(["monthly", "quarterly", "yearly", "once"]).optional(),
      auto_renew: z.boolean().optional(),
      sale_min: z.number().optional(),
      sale_max: z.number().optional(),
      cost_min: z.number().optional(),
      cost_max: z.number().optional(),
      margin_min: z.number().optional(),
      margin_max: z.number().optional(),
      starts_after: z.string().optional().describe("YYYY-MM-DD"),
      starts_before: z.string().optional().describe("YYYY-MM-DD"),
      renews_after: z.string().optional().describe("YYYY-MM-DD"),
      renews_before: z.string().optional().describe("YYYY-MM-DD"),
      cancels_after: z.string().optional().describe("YYYY-MM-DD"),
      cancels_before: z.string().optional().describe("YYYY-MM-DD"),
      invoices_after: z.string().optional().describe("YYYY-MM-DD"),
      invoices_before: z.string().optional().describe("YYYY-MM-DD"),
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/contracts", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "get_contract",
    "Show a contract by id. license_keys included for sales+ only.",
    { id: z.number().int().describe("Contract id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.get(`/contracts/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_contract",
    "Create a contract (sales+). Requires company_id, name, start_date.",
    {
      company_id: z.number().int().describe("Company id"),
      name: z.string().describe("Contract name"),
      start_date: z.string().describe("YYYY-MM-DD"),
      product_id: contractFields.product_id,
      vendor_id: contractFields.vendor_id,
      purchase_bundle_id: contractFields.purchase_bundle_id,
      reference: contractFields.reference,
      type: contractFields.type,
      quantity: contractFields.quantity,
      cost_price: contractFields.cost_price,
      sale_price: contractFields.sale_price,
      currency: contractFields.currency,
      billing_cycle: contractFields.billing_cycle,
      renewal_date: contractFields.renewal_date,
      notice_period_days: contractFields.notice_period_days,
      auto_renew: contractFields.auto_renew,
      status: contractFields.status,
      next_invoice_date: contractFields.next_invoice_date,
      cancelled_at: contractFields.cancelled_at,
      license_keys: contractFields.license_keys,
      notes: contractFields.notes,
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/contracts", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "update_contract",
    "Partial-update a contract (sales+).",
    { id: z.number().int().describe("Contract id"), ...contractFields },
    async ({ id, ...fields }) => {
      try {
        return jsonResult(await client.patch(`/contracts/${id}`, compactBody(fields)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "delete_contract",
    "Delete a contract (admin/manager).",
    { id: z.number().int().describe("Contract id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.delete(`/contracts/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
