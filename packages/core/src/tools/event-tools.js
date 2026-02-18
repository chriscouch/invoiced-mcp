import { z } from "zod";

export const eventTools = [
  {
    name: "list_events",
    description: "List all events. All parameters are optional - can be called without any parameters to get all events.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'timestamp desc', 'created_at asc')").optional(),
      filter: z.object({
        customer: z.string().describe("Filter by customer ID").optional(),
        invoice: z.string().describe("Filter by invoice ID").optional(),
        type: z.string().describe("Filter by event type").optional(),
        user: z.string().describe("Filter by user ID").optional(),
        timestamp_from: z.string().describe("Filter events from this timestamp (YYYY-MM-DD or Unix timestamp)").optional(),
        timestamp_to: z.string().describe("Filter events to this timestamp (YYYY-MM-DD or Unix timestamp)").optional(),
      }).describe("Optional: Filter object to search events by specific criteria").optional(),
      metadata: z.object({}).passthrough().describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_event",
    description: "Get a specific event by ID",
    inputSchema: z.object({
      id: z.string().describe("Event ID"),
    }),
  },
];
