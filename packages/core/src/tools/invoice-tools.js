import { z } from "zod";

const lineItemSchema = z.object({
  name: z.string().describe("Item name"),
  description: z.string().describe("Item description").optional(),
  quantity: z.number().describe("Quantity"),
  unit_cost: z.number().describe("Unit cost"),
  taxable: z.boolean().describe("Is item taxable").optional(),
});

const shipToSchema = z.object({
  name: z.string().describe("Recipient name").optional(),
  attention_to: z.string().describe("Attention to").optional(),
  address1: z.string().describe("Address line 1").optional(),
  address2: z.string().describe("Address line 2").optional(),
  city: z.string().describe("City").optional(),
  state: z.string().describe("State/Province").optional(),
  postal_code: z.string().describe("Postal code").optional(),
  country: z.string().describe("Country (2-letter ISO code)").optional(),
}).describe("Shipping details for the invoice");

export const invoiceTools = [
  {
    name: "list_invoices",
    description: "List all invoices. All parameters are optional - can be called without any parameters to get all invoices.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'date asc', 'created_at desc')").optional(),
      filter: z.object({
        status: z.enum(["bad_debt", "disputed", "draft", "not_sent", "paid", "past_due", "pending", "sent", "viewed", "voided"]).describe("Filter by invoice status").optional(),
        customer: z.string().describe("Filter by customer ID").optional(),
        number: z.string().describe("Filter by invoice number").optional(),
        date_from: z.number().describe("Filter invoices from this date (Unix timestamp)").optional(),
        date_to: z.number().describe("Filter invoices to this date (Unix timestamp)").optional(),
      }).describe("Optional: Filter object to search invoices by specific criteria").optional(),
      metadata: z.record(z.any()).describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_invoice",
    description: "Get a specific invoice by ID",
    inputSchema: z.object({
      id: z.string().describe("Invoice ID"),
    }),
  },
  {
    name: "create_invoice",
    description: "Create a new invoice",
    inputSchema: z.object({
      customer: z.number().describe("Customer ID (the system-generated ID, not customer number)"),
      name: z.string().describe("Invoice name for internal use, defaults to 'Invoice'").optional(),
      number: z.string().describe("Reference number assigned to the invoice, optional, defaults to next # in auto-numbering sequence if not included").optional(),
      purchase_order: z.string().describe("The customer's purchase order number, if there is one").optional(),
      draft: z.boolean().describe("When false, the invoice is considered outstanding, or when true, the invoice is a draft").optional(),
      currency: z.string().describe("3-letter currency code (e.g., USD)").optional(),
      items: z.array(lineItemSchema).describe("Invoice line items"),
      payment_terms: z.string().describe("Payment terms (e.g., 'NET 30')").optional(),
      due_date: z.number().describe("Due date (Unix timestamp - seconds since epoch)").optional(),
      ship_to: shipToSchema.optional(),
      notes: z.string().describe("Invoice notes").optional(),
      date: z.number().describe("Invoice date (Unix timestamp - seconds since epoch)").optional(),
    }),
  },
  {
    name: "update_invoice",
    description: "Update an existing invoice",
    inputSchema: z.object({
      id: z.string().describe("Invoice ID"),
      customer: z.number().describe("Customer ID (the system-generated ID, not customer number)").optional(),
      name: z.string().describe("Invoice name for internal use, defaults to 'Invoice'").optional(),
      number: z.string().describe("Reference number assigned to the invoice, optional, defaults to next # in auto-numbering sequence if not included").optional(),
      purchase_order: z.string().describe("The customer's purchase order number, if there is one").optional(),
      draft: z.boolean().describe("When false, the invoice is considered outstanding, or when true, the invoice is a draft").optional(),
      currency: z.string().describe("3-letter currency code (e.g., USD)").optional(),
      items: z.array(lineItemSchema).describe("Invoice line items").optional(),
      payment_terms: z.string().describe("Payment terms (e.g., 'NET 30')").optional(),
      due_date: z.number().describe("Due date (Unix timestamp - seconds since epoch)").optional(),
      ship_to: shipToSchema.optional(),
      notes: z.string().describe("Invoice notes").optional(),
      date: z.number().describe("Invoice date (Unix timestamp - seconds since epoch)").optional(),
    }),
  },
  {
    name: "send_invoice",
    description: "Send an invoice via email",
    inputSchema: z.object({
      id: z.string().describe("Invoice ID"),
    }),
  },
  {
    name: "void_invoice",
    description: "Void an invoice",
    inputSchema: z.object({
      id: z.string().describe("Invoice ID"),
    }),
  },
];
