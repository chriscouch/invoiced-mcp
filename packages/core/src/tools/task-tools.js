import { z } from "zod";

export const taskTools = [
  {
    name: "list_tasks",
    description: "List all tasks. All parameters are optional - can be called without any parameters to get all tasks.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'due_date asc', 'created_at desc')").optional(),
      filter: z.object({
        customer: z.string().describe("Filter by customer ID").optional(),
        complete: z.boolean().describe("Filter by completion status").optional(),
        user: z.string().describe("Filter by assigned user ID").optional(),
        due_date_from: z.string().describe("Filter tasks with due dates from this date (YYYY-MM-DD)").optional(),
        due_date_to: z.string().describe("Filter tasks with due dates to this date (YYYY-MM-DD)").optional(),
      }).describe("Optional: Filter object to search tasks by specific criteria").optional(),
      metadata: z.object({}).passthrough().describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_task",
    description: "Get a specific task by ID",
    inputSchema: z.object({
      id: z.string().describe("Task ID"),
    }),
  },
  {
    name: "create_task",
    description: "Create a new task",
    inputSchema: z.object({
      name: z.string().describe("Task name/title"),
      action: z.enum(["phone", "email", "letter", "review", "other"]).describe("Task action type"),
      customer: z.number().describe("Customer ID to associate with the task").optional(),
      user: z.number().describe("User ID to assign the task to (optional)").optional(),
      due_date: z.string().describe("Due date for the task (YYYY-MM-DD)").optional(),
      notes: z.string().describe("Task notes or description").optional(),
      complete: z.boolean().describe("Whether the task is complete (defaults to false)").optional(),
      metadata: z.object({}).passthrough().describe("Optional metadata for the task").optional(),
    }),
  },
  {
    name: "update_task",
    description: "Update an existing task",
    inputSchema: z.object({
      id: z.string().describe("Task ID"),
      name: z.string().describe("Task name/title").optional(),
      action: z.enum(["phone", "email", "letter", "review", "other"]).describe("Task action type").optional(),
      customer: z.number().describe("Customer ID to associate with the task").optional(),
      user: z.number().describe("User ID to assign the task to").optional(),
      due_date: z.string().describe("Due date for the task (YYYY-MM-DD)").optional(),
      notes: z.string().describe("Task notes or description").optional(),
      complete: z.boolean().describe("Whether the task is complete").optional(),
      metadata: z.object({}).passthrough().describe("Optional metadata for the task").optional(),
    }),
  },
  {
    name: "delete_task",
    description: "Delete a task",
    inputSchema: z.object({
      id: z.string().describe("Task ID to delete"),
    }),
  },
];
