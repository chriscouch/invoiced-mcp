import { z } from "zod";

const cardSchema = z.object({
  number: z.string().describe("Card number (for sandbox testing only)").optional(),
  exp_month: z.number().describe("Expiration month (1-12)").optional(),
  exp_year: z.number().describe("Expiration year (4 digits)").optional(),
  cvc: z.string().describe("Card security code").optional(),
  name: z.string().describe("Cardholder name").optional(),
}).describe("Card details (if type is 'card')");

const bankAccountSchema = z.object({
  routing_number: z.string().describe("Bank routing number").optional(),
  account_number: z.string().describe("Bank account number").optional(),
  account_type: z.enum(["checking", "savings"]).describe("Account type").optional(),
  name: z.string().describe("Account holder name").optional(),
}).describe("Bank account details (if type is 'bank_account')");

export const paymentSourceTools = [
  {
    name: "list_payment_sources",
    description: "List all payment sources for a customer. Payment sources are stored payment methods like cards or bank accounts.",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID (required)"),
      sort: z.string().describe("Optional: Column to sort by (e.g., 'created_at desc')").optional(),
      filter: z.object({
        type: z.enum(["card", "bank_account"]).describe("Filter by payment source type").optional(),
        gateway: z.string().describe("Filter by payment gateway").optional(),
      }).describe("Optional: Filter object to search payment sources by specific criteria").optional(),
      metadata: z.object({}).passthrough().describe("Optional: Metadata filter object for custom field filtering").optional(),
    }),
  },
  {
    name: "get_payment_source",
    description: "Get a specific payment source by ID",
    inputSchema: z.object({
      id: z.string().describe("Payment source ID"),
    }),
  },
  {
    name: "create_payment_source",
    description: "Create a new payment source for a customer",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID (required)"),
      type: z.enum(["card", "bank_account"]).describe("Payment source type").optional(),
      gateway: z.string().describe("Payment gateway to use").optional(),
      gateway_token: z.string().describe("Token from the payment gateway").optional(),
      card: cardSchema.optional(),
      bank_account: bankAccountSchema.optional(),
      make_default: z.boolean().describe("Set as default payment source for the customer").optional(),
      metadata: z.object({}).passthrough().describe("Custom metadata object").optional(),
    }),
  },
  {
    name: "delete_payment_source",
    description: "Delete a payment source",
    inputSchema: z.object({
      id: z.string().describe("Payment source ID to delete"),
    }),
  },
];
