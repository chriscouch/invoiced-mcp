import { z } from "zod";

export const noteTools = [
  {
    name: "list_notes",
    description: "List notes for a customer or invoice. Must provide either customer_id or invoice_id.",
    inputSchema: z.object({
      customer_id: z.string().describe("Customer ID to list notes for (use this OR invoice_id, not both)").optional(),
      invoice_id: z.string().describe("Invoice ID to list notes for (use this OR customer_id, not both)").optional(),
      sort: z.string().describe("Optional: Column to sort by (e.g., 'created_at desc')").optional(),
      filter: z.object({}).passthrough().describe("Optional: Filter object to search notes by specific criteria").optional(),
      metadata: z.object({}).passthrough().describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "create_note",
    description: "Create a new note associated with a customer or invoice",
    inputSchema: z.object({
      customer_id: z.string().describe("Customer ID to associate note with (use this OR invoice_id, not both)").optional(),
      invoice_id: z.string().describe("Invoice ID to associate note with (use this OR customer_id, not both)").optional(),
      notes: z.string().describe("Note content/text"),
      metadata: z.object({}).passthrough().describe("Optional metadata for the note").optional(),
    }),
  },
  {
    name: "update_note",
    description: "Update an existing note",
    inputSchema: z.object({
      id: z.string().describe("Note ID"),
      notes: z.string().describe("Updated note content/text").optional(),
      metadata: z.object({}).passthrough().describe("Optional metadata for the note").optional(),
    }),
  },
  {
    name: "delete_note",
    description: "Delete a note",
    inputSchema: z.object({
      id: z.string().describe("Note ID to delete"),
    }),
  },
];
