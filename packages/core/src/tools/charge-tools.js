import { z } from "zod";

export const chargeTools = [
  {
    name: "list_charges",
    description: "List all charges. Charges are one-time fees that can be added to invoices. All parameters are optional.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'date asc', 'created_at desc')").optional(),
      filter: z.object({
        customer: z.string().describe("Filter by customer ID").optional(),
        paid: z.boolean().describe("Filter by paid status").optional(),
        refunded: z.boolean().describe("Filter by refunded status").optional(),
      }).describe("Optional: Filter object to search charges by specific criteria").optional(),
      metadata: z.object({}).passthrough().describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_charge",
    description: "Get a specific charge by ID",
    inputSchema: z.object({
      id: z.string().describe("Charge ID"),
    }),
  },
  {
    name: "create_charge",
    description: "Create a new charge (one-time fee) to be added to an invoice",
    inputSchema: z.object({
      customer: z.number().describe("Customer ID (required)"),
      amount: z.number().describe("Charge amount (required)"),
      currency: z.string().describe("3-letter currency code (e.g., USD)").optional(),
      description: z.string().describe("Description of the charge").optional(),
      invoice: z.number().describe("Invoice ID to add the charge to (optional)").optional(),
      date: z.string().describe("Charge date (YYYY-MM-DD)").optional(),
      quantity: z.number().describe("Quantity (default: 1)").optional(),
      item: z.string().describe("Catalog item ID to use for this charge").optional(),
      metadata: z.object({}).passthrough().describe("Custom metadata object").optional(),
    }),
  },
];
