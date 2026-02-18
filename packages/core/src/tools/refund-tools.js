import { z } from "zod";

export const refundTools = [
  {
    name: "list_refunds",
    description: "List all refunds. Refunds are returns of payments to customers. All parameters are optional.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'date asc', 'created_at desc')").optional(),
      filter: z.object({
        customer: z.string().describe("Filter by customer ID").optional(),
        payment: z.string().describe("Filter by payment ID").optional(),
        status: z.enum(["pending", "succeeded", "failed"]).describe("Filter by refund status").optional(),
      }).describe("Optional: Filter object to search refunds by specific criteria").optional(),
      metadata: z.object({}).passthrough().describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_refund",
    description: "Get a specific refund by ID",
    inputSchema: z.object({
      id: z.string().describe("Refund ID"),
    }),
  },
  {
    name: "create_refund",
    description: "Create a new refund for a payment",
    inputSchema: z.object({
      payment: z.number().describe("Payment ID to refund (required)"),
      amount: z.number().describe("Amount to refund (required). Must be less than or equal to the payment amount."),
      notes: z.string().describe("Notes about the refund").optional(),
      reason: z.enum(["duplicate", "fraudulent", "requested_by_customer", "other"]).describe("Reason for the refund").optional(),
      metadata: z.object({}).passthrough().describe("Custom metadata object").optional(),
    }),
  },
];
