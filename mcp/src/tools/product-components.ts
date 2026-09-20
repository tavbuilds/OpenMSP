import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

const componentFields = {
  product_id: z.number().int().optional().describe("Composite product (package) id"),
  component_id: z.number().int().optional().describe("Catalog product used as a part"),
  quantity: z.number().int().min(1).optional().nullable(),
};

export function registerProductComponents(server: McpServer, client: MspClient): void {
  server.tool(
    "list_product_components",
    "List product composition (BOM) links. Filter by product_id or component_id.",
    {
      ...pagination,
      product_id: z.number().int().optional(),
      component_id: z.number().int().optional(),
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/product-components", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "list_product_bom",
    "List components nested under a product (product_id from path).",
    {
      product_id: z.number().int().describe("Composite product id"),
      per_page: pagination.per_page,
      page: pagination.page,
    },
    async ({ product_id, ...query }) => {
      try {
        return jsonResult(await client.get(`/products/${product_id}/components`, query));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "get_product_component",
    "Show a single product-component link by id.",
    { id: z.number().int().describe("ProductComponent id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.get(`/product-components/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_product_component",
    "Attach a catalog product as a component of a package (sales+).",
    {
      product_id: z.number().int().describe("Composite product id"),
      component_id: z.number().int().describe("Part product id"),
      quantity: z.number().int().min(1).optional(),
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/product-components", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_product_bom_item",
    "Attach a component via nested route (product_id from path).",
    {
      product_id: z.number().int().describe("Composite product id"),
      component_id: z.number().int().describe("Part product id"),
      quantity: z.number().int().min(1).optional(),
    },
    async ({ product_id, ...body }) => {
      try {
        return jsonResult(
          await client.post(`/products/${product_id}/components`, compactBody(body)),
        );
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "update_product_component",
    "Partial-update a product-component link (sales+).",
    { id: z.number().int().describe("ProductComponent id"), ...componentFields },
    async ({ id, ...fields }) => {
      try {
        return jsonResult(await client.patch(`/product-components/${id}`, compactBody(fields)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "delete_product_component",
    "Delete a product-component link (admin/manager).",
    { id: z.number().int().describe("ProductComponent id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.delete(`/product-components/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
