import { z } from "zod";

const recipientSchema = z.object({
  name: z.string().describe("Recipient name").optional(),
  email_address: z.string().describe("Email address"),
});

const attachmentSchema = z.object({
  id: z.number().describe("Attachment ID").optional(),
  name: z.string().describe("Attachment name").optional(),
});

export const emailTools = [
  {
    name: "list_inboxes",
    description: "List email inboxes",
    inputSchema: z.object({
      limit: z.number().describe("Number of inboxes to return").optional(),
      updated_after: z.number().describe("Unix timestamp - only get records updated after this time").optional(),
    }),
  },
  {
    name: "get_inbox",
    description: "Get a specific inbox by ID",
    inputSchema: z.object({
      id: z.string().describe("Inbox ID"),
    }),
  },
  {
    name: "send_email",
    description: "Send an email from an inbox",
    inputSchema: z.object({
      inbox_id: z.string().describe("Inbox ID to send from"),
      to: z.array(recipientSchema).describe("Recipients (array of objects with name and email_address)"),
      subject: z.string().describe("Email subject"),
      message: z.string().describe("Email message body"),
      cc: z.array(recipientSchema).describe("CC recipients").optional(),
      bcc: z.array(recipientSchema).describe("BCC recipients").optional(),
      related_to_type: z.string().describe("Type of related object (invoice, customer, etc.)").optional(),
      related_to_id: z.number().describe("ID of related object").optional(),
      thread_id: z.number().describe("Thread ID if replying to existing thread").optional(),
      reply_to_id: z.number().describe("Email ID being replied to").optional(),
      attachments: z.array(attachmentSchema).describe("Email attachments").optional(),
      status: z.enum(["open", "closed"]).describe("Thread status").optional(),
    }),
  },
  {
    name: "list_inbox_threads",
    description: "List email threads for an inbox",
    inputSchema: z.object({
      inbox_id: z.string().describe("Inbox ID"),
      limit: z.number().describe("Number of threads to return").optional(),
      status: z.enum(["open", "closed"]).describe("Filter by thread status").optional(),
    }),
  },
  {
    name: "get_email_thread",
    description: "Get a specific email thread by ID",
    inputSchema: z.object({
      thread_id: z.string().describe("Thread ID"),
    }),
  },
  {
    name: "update_email_thread",
    description: "Update an email thread (e.g., change status)",
    inputSchema: z.object({
      thread_id: z.string().describe("Thread ID"),
      status: z.enum(["open", "closed"]).describe("Thread status").optional(),
      subject: z.string().describe("Thread subject").optional(),
    }),
  },
  {
    name: "get_email_thread_by_document",
    description: "Get email thread for a specific document (invoice, estimate, etc.)",
    inputSchema: z.object({
      document_type: z.enum(["invoice", "estimate", "credit_note", "statement"]).describe("Type of document"),
      document_id: z.string().describe("Document ID"),
    }),
  },
  {
    name: "list_inbox_emails",
    description: "List emails in an inbox",
    inputSchema: z.object({
      inbox_id: z.string().describe("Inbox ID"),
      limit: z.number().describe("Number of emails to return").optional(),
      direction: z.enum(["inbound", "outbound"]).describe("Email direction").optional(),
    }),
  },
  {
    name: "list_thread_emails",
    description: "List emails in a specific thread",
    inputSchema: z.object({
      thread_id: z.string().describe("Thread ID"),
      limit: z.number().describe("Number of emails to return").optional(),
    }),
  },
  {
    name: "get_inbox_email",
    description: "Get a specific email by ID",
    inputSchema: z.object({
      email_id: z.string().describe("Email ID"),
    }),
  },
  {
    name: "get_email_message",
    description: "Get the full message body of an email",
    inputSchema: z.object({
      email_id: z.string().describe("Email ID"),
    }),
  },
  {
    name: "list_thread_notes",
    description: "List notes for an email thread",
    inputSchema: z.object({
      thread_id: z.string().describe("Thread ID"),
      limit: z.number().describe("Number of notes to return").optional(),
    }),
  },
  {
    name: "create_thread_note",
    description: "Create a note on an email thread",
    inputSchema: z.object({
      thread_id: z.string().describe("Thread ID"),
      content: z.string().describe("Note content"),
      private: z.boolean().describe("Whether the note is private").optional(),
    }),
  },
  {
    name: "list_email_attachments",
    description: "List attachments for an email",
    inputSchema: z.object({
      email_id: z.string().describe("Email ID"),
    }),
  },
  {
    name: "email_autocomplete",
    description: "Get email address autocomplete suggestions",
    inputSchema: z.object({
      query: z.string().describe("Search query for email autocomplete"),
      limit: z.number().describe("Number of suggestions to return").optional(),
    }),
  },
];
