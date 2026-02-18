import { z } from "zod";

const lineItemSchema = z.object({
  name: z.string().describe("Item name"),
  description: z.string().describe("Item description").optional(),
  quantity: z.number().describe("Quantity"),
  unit_cost: z.number().describe("Unit cost"),
});

export const creditNoteTools = [
  {
    name: "list_credit_notes",
    description: "List credit notes with optional filters",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'date asc', 'created_at desc')").optional(),
      filter: z.object({
        customer: z.string().describe("Filter by customer ID").optional(),
        status: z.enum(["draft", "open", "closed", "voided"]).describe("Filter by credit note status").optional(),
        invoice: z.string().describe("Filter by invoice ID").optional(),
      }).describe("Optional: Filter object to search credit notes by specific criteria").optional(),
      metadata: z.record(z.any()).describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_credit_note",
    description: "Get a specific credit note by ID",
    inputSchema: z.object({
      id: z.string().describe("Credit note ID"),
    }),
  },
  {
    name: "create_credit_note",
    description: "Create a new credit note",
    inputSchema: z.object({
      customer: z.number().describe("Customer ID (the system-generated ID, not customer number)"),
      currency: z.string().describe("3-letter currency code (e.g., USD)").optional(),
      items: z.array(lineItemSchema).describe("Credit note line items"),
      date: z.string().describe("Credit note date").optional(),
    }),
  },
  {
    name: "send_credit_note",
    description: "Send a credit note via email",
    inputSchema: z.object({
      id: z.string().describe("Credit note ID"),
    }),
  },
  {
    name: "void_credit_note",
    description: "Void a credit note",
    inputSchema: z.object({
      id: z.string().describe("Credit note ID"),
    }),
  },
];
