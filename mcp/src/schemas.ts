import { z } from "zod";

export const pagination = {
  search: z.string().optional().describe("Case-insensitive text search"),
  per_page: z.number().int().min(1).max(100).optional().describe("Page size (default 15, max 100)"),
  page: z.number().int().min(1).optional().describe("Page number"),
  sort: z.string().optional().describe("Sort column from the resource allowlist"),
  order: z.enum(["asc", "desc"]).optional().describe("Sort direction (default asc when sort is set)"),
};
