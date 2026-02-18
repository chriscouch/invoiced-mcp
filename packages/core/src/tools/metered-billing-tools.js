import { z } from "zod";

export const meteredBillingTools = [
  {
    name: "list_metered_billings",
    description: "List all metered billing records. All parameters are optional - can be called without any parameters to get all metered billing records.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'timestamp desc', 'created_at asc')").optional(),
      filter: z.object({
        subscription: z.string().describe("Filter by subscription ID").optional(),
        plan: z.string().describe("Filter by plan ID").optional(),
        customer: z.string().describe("Filter by customer ID").optional(),
        timestamp_from: z.string().describe("Filter records from this timestamp (YYYY-MM-DD or Unix timestamp)").optional(),
        timestamp_to: z.string().describe("Filter records to this timestamp (YYYY-MM-DD or Unix timestamp)").optional(),
      }).describe("Optional: Filter object to search metered billing records by specific criteria").optional(),
      metadata: z.object({}).passthrough().describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "create_metered_billing",
    description: "Create a new metered billing record for usage-based billing",
    inputSchema: z.object({
      subscription: z.number().describe("Subscription ID this usage applies to"),
      plan: z.number().describe("Plan ID this usage applies to"),
      quantity: z.number().describe("Quantity/amount of usage"),
      timestamp: z.string().describe("Timestamp when usage occurred (YYYY-MM-DD or Unix timestamp)"),
      notes: z.string().describe("Optional notes about the usage").optional(),
      metadata: z.object({}).passthrough().describe("Optional metadata for the metered billing record").optional(),
    }),
  },
];
