import { z } from "zod";

export const paymentTools = [
  {
    name: "list_payments",
    description: "List all payments. All parameters are optional - can be called without any parameters to get all payments.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'date asc', 'created_at desc')").optional(),
      filter: z.object({
        customer: z.string().describe("Filter by customer ID").optional(),
        invoice: z.string().describe("Filter by invoice ID").optional(),
        status: z.string().describe("Filter by payment status").optional(),
        method: z.string().describe("Filter by payment method").optional(),
        date_from: z.string().describe("Filter payments from this date (YYYY-MM-DD)").optional(),
        date_to: z.string().describe("Filter payments to this date (YYYY-MM-DD)").optional(),
      }).describe("Optional: Filter object to search payments by specific criteria").optional(),
      metadata: z.record(z.any()).describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_payment",
    description: "Get a specific payment by ID",
    inputSchema: z.object({
      id: z.string().describe("Payment ID"),
    }),
  },
  {
    name: "create_payment",
    description: "Create a new payment",
    inputSchema: z.object({
      customer: z.number().describe("Customer ID (the system-generated ID, not customer number)").optional(),
      amount: z.number().describe("Payment amount"),
      currency: z.string().describe("3-letter currency code (e.g., USD)").optional(),
      method: z.enum(["credit_card", "ach", "check", "cash", "wire_transfer", "other"]).describe("Payment method").optional(),
      date: z.string().describe("Payment date (YYYY-MM-DD)").optional(),
      notes: z.string().describe("Payment notes").optional(),
      reference: z.string().describe("External reference number").optional(),
      invoice: z.string().describe("Invoice ID to apply payment to (optional)").optional(),
    }),
  },
];
