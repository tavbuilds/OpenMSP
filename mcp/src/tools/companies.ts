import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

const companyFields = {
  name: z.string().optional().describe("Company name (required on create)"),
  kvk_number: z.string().optional().nullable(),
  vat_number: z.string().optional().nullable(),
  email: z.string().optional().nullable(),
  phone: z.string().optional().nullable(),
  address: z.string().optional().nullable(),
  postal_code: z.string().optional().nullable(),
  city: z.string().optional().nullable(),
  country: z.string().optional().nullable(),
  notes: z.string().optional().nullable(),
};

export function registerCompanies(server: McpServer, client: MspClient): void {
  server.tool(
    "list_companies",
    "List/search companies with optional city/country filters.",
    {
      ...pagination,
      city: z.string().optional().describe("Partial, case-insensitive city match"),
      country: z.string().optional().describe("Exact country code/name"),
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/companies", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "get_company",
    "Show a single company by id (includes contact/contract counts).",
    { id: z.number().int().describe("Company id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.get(`/companies/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_company",
    "Create a company (sales+ role).",
    {
      name: z.string().describe("Company name"),
      kvk_number: companyFields.kvk_number,
      vat_number: companyFields.vat_number,
      email: companyFields.email,
      phone: companyFields.phone,
      address: companyFields.address,
      postal_code: companyFields.postal_code,
      city: companyFields.city,
      country: companyFields.country,
      notes: companyFields.notes,
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/companies", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "update_company",
    "Partial-update a company (sales+). PATCH semantics.",
    { id: z.number().int().describe("Company id"), ...companyFields },
    async ({ id, ...fields }) => {
      try {
        return jsonResult(await client.patch(`/companies/${id}`, compactBody(fields)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "delete_company",
    "Delete a company (admin/manager).",
    { id: z.number().int().describe("Company id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.delete(`/companies/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
