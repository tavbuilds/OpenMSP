import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

const contactFields = {
  company_id: z.number().int().optional().describe("Company FK (required on top-level create)"),
  name: z.string().optional().describe("Contact name (required on create)"),
  email: z.string().optional().nullable(),
  phone: z.string().optional().nullable(),
  job_title: z.string().optional().nullable(),
  is_primary: z.boolean().optional().nullable(),
};

export function registerContacts(server: McpServer, client: MspClient): void {
  server.tool(
    "list_contacts",
    "List/search contacts. Filter by company_id and is_primary.",
    {
      ...pagination,
      company_id: z.number().int().optional(),
      is_primary: z.boolean().optional(),
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/contacts", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "list_company_contacts",
    "List contacts nested under a company (company_id from path).",
    {
      company_id: z.number().int().describe("Company id"),
      search: pagination.search,
      is_primary: z.boolean().optional(),
      per_page: pagination.per_page,
      page: pagination.page,
    },
    async ({ company_id, ...query }) => {
      try {
        return jsonResult(await client.get(`/companies/${company_id}/contacts`, query));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "get_contact",
    "Show a single contact by id.",
    { id: z.number().int().describe("Contact id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.get(`/contacts/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_contact",
    "Create a contact (sales+). Requires company_id + name.",
    {
      company_id: z.number().int().describe("Company id"),
      name: z.string().describe("Contact name"),
      email: contactFields.email,
      phone: contactFields.phone,
      job_title: contactFields.job_title,
      is_primary: contactFields.is_primary,
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/contacts", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_company_contact",
    "Create a contact on a company via nested route (company_id from path).",
    {
      company_id: z.number().int().describe("Company id"),
      name: z.string().describe("Contact name"),
      email: contactFields.email,
      phone: contactFields.phone,
      job_title: contactFields.job_title,
      is_primary: contactFields.is_primary,
    },
    async ({ company_id, ...body }) => {
      try {
        return jsonResult(
          await client.post(`/companies/${company_id}/contacts`, compactBody(body)),
        );
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "update_contact",
    "Partial-update a contact (sales+).",
    { id: z.number().int().describe("Contact id"), ...contactFields },
    async ({ id, ...fields }) => {
      try {
        return jsonResult(await client.patch(`/contacts/${id}`, compactBody(fields)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "delete_contact",
    "Delete a contact (admin/manager).",
    { id: z.number().int().describe("Contact id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.delete(`/contacts/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
