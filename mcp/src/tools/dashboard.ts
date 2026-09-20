import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

export function registerDashboard(server: McpServer, client: MspClient): void {
  server.tool(
    "get_dashboard",
    "Portfolio dashboard metrics (MRR/ARR/margin/active contracts/upcoming renewals). Filament PortfolioStats parity.",
    {},
    async () => {
      try {
        return jsonResult(await client.get("/dashboard"));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "list_upcoming_renewals",
    "Active contracts with renewal_date within the next N days (default 30, max 365). Prefer over Filament scraping.",
    {
      days: z.number().int().min(1).max(365).optional().describe("Horizon in days (default 30)"),
      per_page: pagination.per_page,
      page: pagination.page,
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/contracts/upcoming-renewals", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
