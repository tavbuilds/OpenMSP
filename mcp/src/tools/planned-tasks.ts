import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { MspClient } from "../client.js";
import { compactBody, errorResult, jsonResult } from "../client.js";
import { pagination } from "../schemas.js";

const kind = z
  .enum(["relocation", "migration", "onsite", "project", "other"])
  .optional()
  .describe("relocation | migration | onsite | project | other");

const status = z
  .enum(["planned", "in_progress", "blocked", "done", "cancelled"])
  .optional();

const priority = z.enum(["low", "normal", "high", "urgent"]).optional();

const fields = {
  title: z.string().optional().describe("Task title (required on create)"),
  company_id: z.number().int().optional().nullable().describe("Customer id"),
  assigned_user_id: z.number().int().optional().nullable(),
  kind,
  status,
  priority,
  due_on: z.string().optional().describe("Deadline YYYY-MM-DD (required on create)"),
  location_from: z.string().optional().nullable(),
  location_to: z.string().optional().nullable(),
  notes: z.string().optional().nullable(),
};

export function registerPlannedTasks(server: McpServer, client: MspClient): void {
  server.tool(
    "list_planned_tasks",
    "List/search planned work (moves, migrations, on-site jobs).",
    {
      ...pagination,
      company_id: z.number().int().optional(),
      assigned_user_id: z.number().int().optional(),
      kind,
      status,
      priority,
      due_after: z.string().optional(),
      due_before: z.string().optional(),
      open: z.boolean().optional().describe("Only tasks that are not done/cancelled"),
      overdue: z.boolean().optional(),
    },
    async (args) => {
      try {
        return jsonResult(await client.get("/planned-tasks", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "list_upcoming_planned_tasks",
    "Open planned tasks due within N days (includes overdue).",
    { days: z.number().int().min(1).max(365).optional().describe("Horizon, default 60") },
    async (args) => {
      try {
        return jsonResult(await client.get("/planned-tasks/upcoming", args));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "get_planned_task",
    "Show a planned task by id.",
    { id: z.number().int().describe("Planned task id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.get(`/planned-tasks/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "create_planned_task",
    "Create a planned task (sales+).",
    {
      title: z.string().describe("Task title"),
      due_on: z.string().describe("Deadline YYYY-MM-DD"),
      company_id: fields.company_id,
      assigned_user_id: fields.assigned_user_id,
      kind,
      status,
      priority,
      location_from: fields.location_from,
      location_to: fields.location_to,
      notes: fields.notes,
    },
    async (args) => {
      try {
        return jsonResult(await client.post("/planned-tasks", compactBody(args)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "update_planned_task",
    "Partial-update a planned task (sales+).",
    { id: z.number().int().describe("Planned task id"), ...fields },
    async ({ id, ...rest }) => {
      try {
        return jsonResult(await client.patch(`/planned-tasks/${id}`, compactBody(rest)));
      } catch (e) {
        return errorResult(e);
      }
    },
  );

  server.tool(
    "delete_planned_task",
    "Delete a planned task (admin/manager).",
    { id: z.number().int().describe("Planned task id") },
    async ({ id }) => {
      try {
        return jsonResult(await client.delete(`/planned-tasks/${id}`));
      } catch (e) {
        return errorResult(e);
      }
    },
  );
}
