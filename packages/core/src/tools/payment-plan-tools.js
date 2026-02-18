import { z } from "zod";

const installmentSchema = z.object({
  date: z.string().describe("Installment due date (YYYY-MM-DD)"),
  amount: z.number().describe("Installment amount"),
});

export const paymentPlanTools = [
  {
    name: "list_payment_plans",
    description: "List all payment plans. All parameters are optional - can be called without any parameters to get all payment plans.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'created_at desc', 'status asc')").optional(),
      filter: z.object({
        customer: z.string().describe("Filter by customer ID").optional(),
        invoice: z.string().describe("Filter by invoice ID").optional(),
        status: z.string().describe("Filter by payment plan status").optional(),
      }).describe("Optional: Filter object to search payment plans by specific criteria").optional(),
      metadata: z.object({}).passthrough().describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_payment_plan",
    description: "Get a specific payment plan by ID",
    inputSchema: z.object({
      id: z.string().describe("Payment plan ID"),
    }),
  },
  {
    name: "create_payment_plan",
    description: "Create a new payment plan for an invoice",
    inputSchema: z.object({
      invoice_id: z.number().describe("Invoice ID to create payment plan for"),
      installments: z.array(installmentSchema).describe("Array of installment objects with date and amount"),
      approval: z.enum(["none", "auto", "manual"]).describe("Approval requirement for payment plan").optional(),
      metadata: z.object({}).passthrough().describe("Optional metadata for the payment plan").optional(),
    }),
  },
  {
    name: "update_payment_plan",
    description: "Update an existing payment plan",
    inputSchema: z.object({
      id: z.string().describe("Payment plan ID"),
      installments: z.array(installmentSchema).describe("Array of installment objects with date and amount").optional(),
      approval: z.enum(["none", "auto", "manual"]).describe("Approval requirement for payment plan").optional(),
      metadata: z.object({}).passthrough().describe("Optional metadata for the payment plan").optional(),
    }),
  },
  {
    name: "delete_payment_plan",
    description: "Delete a payment plan",
    inputSchema: z.object({
      id: z.string().describe("Payment plan ID to delete"),
    }),
  },
];
