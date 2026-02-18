import { z } from "zod";

export const creditBalanceTools = [
  {
    name: "list_credit_balances",
    description: "List all credit balances. All parameters are optional - can be called without any parameters to get all credit balances.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'created_at desc', 'balance asc')").optional(),
      filter: z.object({
        customer: z.string().describe("Filter by customer ID").optional(),
        currency: z.string().describe("Filter by currency code").optional(),
      }).describe("Optional: Filter object to search credit balances by specific criteria").optional(),
      metadata: z.object({}).describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_credit_balance",
    description: "Get a specific credit balance by ID",
    inputSchema: z.object({
      id: z.string().describe("Credit balance ID"),
    }),
  },
  {
    name: "create_credit_balance",
    description: "Create a new credit balance adjustment for a customer. Customer must be an integer ID, amount must be a number.",
    inputSchema: z.object({
      customer: z.string().describe("Customer ID (e.g., 10565872)"),
      amount: z.number().describe("Credit amount (e.g., 400)"),
      currency: z.string().describe("Currency code in lowercase (e.g., 'usd')").optional(),
      notes: z.string().describe("Optional notes about the credit balance").optional(),
    }),
  },
];
