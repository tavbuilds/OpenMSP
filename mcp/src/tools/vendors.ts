import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

const vendorFields = {
  name: z.string().optional().describe("Vendor name (required on create)"),
  website: z.string().optional().nullable(),
  email: z.string().optional().nullable(),
  phone: z.string().optional().nullable(),
  notes: z.string().optional().nullable(),
};

export function registerVendors(server: McpServer, client: MspClient): void {
  server.tool(
    "list_vendors",
    "List/search vendors.",
    { ...pagination },
    async (args) => {
      try {
        return jsonResult(await client.get("/vendors", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "get_vendor",
    "Show a vendor by id.",
    { id: z.number().int().describe("Vendor id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.get(`/vendors/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_vendor",
    "Create a vendor (sales+).",
    {
      name: z.string().describe("Vendor name"),
      website: vendorFields.website,
      email: vendorFields.email,
      phone: vendorFields.phone,
      notes: vendorFields.notes,
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/vendors", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "update_vendor",
    "Partial-update a vendor (sales+).",
    { id: z.number().int().describe("Vendor id"), ...vendorFields },
    async ({ id, ...fields }) => {
      try {
        return jsonResult(await client.patch(`/vendors/${id}`, compactBody(fields)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "delete_vendor",
    "Delete a vendor (admin/manager).",
    { id: z.number().int().describe("Vendor id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.delete(`/vendors/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
