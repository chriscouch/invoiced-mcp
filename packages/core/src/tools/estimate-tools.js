import { z } from "zod";

const lineItemSchema = z.object({
  name: z.string().describe("Item name"),
  description: z.string().describe("Item description").optional(),
  quantity: z.number().describe("Quantity"),
  unit_cost: z.number().describe("Unit cost"),
  taxable: z.boolean().describe("Is item taxable").optional(),
});

export const estimateTools = [
  {
    name: "list_estimates",
    description: "List estimates with optional filters",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'date asc', 'created_at desc')").optional(),
      filter: z.object({
        status: z.enum(["draft", "sent", "viewed", "approved", "declined"]).describe("Filter by estimate status").optional(),
        customer: z.string().describe("Filter by customer ID").optional(),
        date_from: z.string().describe("Filter estimates from this date (YYYY-MM-DD)").optional(),
        date_to: z.string().describe("Filter estimates to this date (YYYY-MM-DD)").optional(),
      }).describe("Optional: Filter object to search estimates by specific criteria").optional(),
      metadata: z.record(z.any()).describe("Optional: Metadata filter object for custom field filtering").optional(),
      limit: z.number().describe("Number of estimates to return (optional)").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_estimate",
    description: "Get a specific estimate by ID",
    inputSchema: z.object({
      id: z.string().describe("Estimate ID"),
    }),
  },
  {
    name: "create_estimate",
    description: "Create a new estimate",
    inputSchema: z.object({
      customer: z.number().describe("Customer ID (the system-generated ID, not customer number)"),
      currency: z.string().describe("3-letter currency code (e.g., USD)").optional(),
      items: z.array(lineItemSchema).describe("Estimate line items"),
      expiration_date: z.string().describe("Estimate expiration date (YYYY-MM-DD)").optional(),
      notes: z.string().describe("Estimate notes").optional(),
      date: z.string().describe("Date the estimate was issued (YYYY-MM-DD)").optional(),
    }),
  },
  {
    name: "update_estimate",
    description: "Update an existing estimate",
    inputSchema: z.object({
      id: z.string().describe("Estimate ID"),
      customer: z.number().describe("Customer ID (the system-generated ID, not customer number)").optional(),
      currency: z.string().describe("3-letter currency code (e.g., USD)").optional(),
      items: z.array(lineItemSchema).describe("Estimate line items").optional(),
      expiration_date: z.string().describe("Estimate expiration date (YYYY-MM-DD)").optional(),
      notes: z.string().describe("Estimate notes").optional(),
      date: z.string().describe("Date the estimate was issued (YYYY-MM-DD)").optional(),
    }),
  },
  {
    name: "send_estimate",
    description: "Send an estimate via email",
    inputSchema: z.object({
      id: z.string().describe("Estimate ID"),
    }),
  },
  {
    name: "void_estimate",
    description: "Void an estimate",
    inputSchema: z.object({
      id: z.string().describe("Estimate ID"),
    }),
  },
];
