import { z } from "zod";

const tierSchema = z.object({
  start_quantity: z.number().describe("Tier start quantity"),
  end_quantity: z.number().optional().describe("Tier end quantity"),
  unit_cost: z.number().describe("Unit cost for this tier"),
});

export const planTools = [
  {
    name: "list_plans",
    description: "List subscription plans with optional filters",
    inputSchema: z.object({
      limit: z.number().optional().describe("Number of plans to return (optional)"),
      sort: z.string().optional().describe("Optional: Column to sort by (e.g., 'name asc', 'created_at desc')"),
      filter: z.object({
        interval: z.enum(["month", "year", "week", "day"]).optional().describe("Filter by billing interval"),
        currency: z.string().optional().describe("Filter by currency code (e.g., USD)"),
      }).optional().describe("Optional: Filter object to search plans by specific criteria"),
      metadata: z.object({}).passthrough().optional().describe("Optional: Metadata filter object for custom field filtering"),
      updated_after: z.number().optional().describe("Optional: Unix timestamp - only gets records updated after this time"),
    }),
  },
  {
    name: "get_plan",
    description: "Get a specific plan by ID",
    inputSchema: z.object({
      id: z.string().describe("Plan ID"),
    }),
  },
  {
    name: "create_plan",
    description: "Create a new subscription plan",
    inputSchema: z.object({
      id: z.string().describe("Unique plan identifier"),
      name: z.string().describe("Plan name"),
      catalog_item: z.string().describe("Catalog item ID"),
      amount: z.number().describe("Plan amount in cents"),
      interval: z.enum(["day", "week", "month", "year"]).describe("Billing interval"),
      interval_count: z.number().optional().describe("Number of intervals between billing"),
      currency: z.string().optional().describe("3-letter currency code"),
      pricing_mode: z.enum(["per_unit", "volume", "tiered", "custom"]).optional().describe("Pricing mode"),
      tiers: z.array(tierSchema).optional().describe("Pricing tiers (for tiered pricing)"),
      metadata: z.object({}).passthrough().optional().describe("Custom metadata"),
    }),
  },
  {
    name: "update_plan",
    description: "Update a subscription plan",
    inputSchema: z.object({
      id: z.string().describe("Plan ID"),
      name: z.string().optional().describe("Plan name"),
      metadata: z.object({}).passthrough().optional().describe("Custom metadata"),
    }),
  },
  {
    name: "delete_plan",
    description: "Delete a subscription plan",
    inputSchema: z.object({
      id: z.string().describe("Plan ID"),
    }),
  },
];
