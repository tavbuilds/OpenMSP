import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

const productFields = {
  vendor_id: z.number().int().optional().nullable(),
  name: z.string().optional().describe("Product name (required on create)"),
  sku: z.string().optional().nullable(),
  type: z.string().optional().nullable().describe("Product type enum"),
  default_cost_price: z.number().min(0).optional().nullable(),
  default_sale_price: z.number().min(0).optional().nullable(),
  currency: z.string().length(3).optional().nullable(),
  billing_cycle: z.enum(["monthly", "quarterly", "yearly", "once"]).optional().nullable(),
  description: z.string().optional().nullable(),
  active: z.boolean().optional().nullable(),
};

export function registerProducts(server: McpServer, client: MspClient): void {
  server.tool(
    "list_products",
    "List/search products. Filter by vendor, type, billing_cycle, active, price ranges.",
    {
      ...pagination,
      vendor_id: z.number().int().optional(),
      type: z.string().optional().describe("Product type enum"),
      billing_cycle: z.enum(["monthly", "quarterly", "yearly", "once"]).optional(),
      active: z.boolean().optional(),
      sale_min: z.number().optional(),
      sale_max: z.number().optional(),
      cost_min: z.number().optional(),
      cost_max: z.number().optional(),
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/products", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "get_product",
    "Show a product by id.",
    { id: z.number().int().describe("Product id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.get(`/products/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_product",
    "Create a product (sales+).",
    {
      name: z.string().describe("Product name"),
      vendor_id: productFields.vendor_id,
      sku: productFields.sku,
      type: productFields.type,
      default_cost_price: productFields.default_cost_price,
      default_sale_price: productFields.default_sale_price,
      currency: productFields.currency,
      billing_cycle: productFields.billing_cycle,
      description: productFields.description,
      active: productFields.active,
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/products", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "update_product",
    "Partial-update a product (sales+).",
    { id: z.number().int().describe("Product id"), ...productFields },
    async ({ id, ...fields }) => {
      try {
        return jsonResult(await client.patch(`/products/${id}`, compactBody(fields)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "delete_product",
    "Delete a product (admin/manager).",
    { id: z.number().int().describe("Product id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.delete(`/products/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
