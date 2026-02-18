import { z } from "zod";

const templateOptionsSchema = z.object({
  send_on_subscription_invoice: z.boolean().describe("Send when subscription invoice is created").optional(),
  send_on_issue: z.boolean().describe("Send when document is issued").optional(),
  send_once_paid: z.boolean().describe("Send when fully paid").optional(),
  send_on_charge: z.boolean().describe("Send on charge event").optional(),
  button_text: z.string().describe("Call-to-action button text").optional(),
  send_reminder_days: z.number().describe("Days before due date to send reminder").optional(),
  attach_pdf: z.boolean().describe("Attach PDF version of document").optional(),
  attach_secondary_files: z.boolean().describe("Attach secondary files").optional(),
}).describe("Template options");

export const emailTemplateTools = [
  {
    name: "list_email_templates",
    description: "List email templates",
    inputSchema: z.object({
      paginate: z.enum(["offset", "none"]).describe("Pagination mode: 'offset' (default) for paginated results, 'none' to return all results").optional(),
      per_page: z.number().describe("Number of templates to return per page when paginate='offset' (default 25, max 100)").optional(),
      page: z.number().describe("Page number for pagination when paginate='offset' (starts at 1)").optional(),
      filter: z.object({
        type: z.enum(["invoice", "credit_note", "payment_plan", "estimate", "subscription", "transaction", "statement", "chasing", "sign_in_link"]).describe("Template type").optional(),
        name: z.string().describe("Template name").optional(),
      }).describe("Filter criteria").optional(),
    }),
  },
  {
    name: "get_email_template",
    description: "Get a specific email template by ID",
    inputSchema: z.object({
      template_id: z.string().describe("Email template ID"),
    }),
  },
  {
    name: "create_email_template",
    description: "Create a new email template",
    inputSchema: z.object({
      id: z.string().describe("Template ID (e.g., 'new_invoice_email', 'payment_receipt_email')").optional(),
      name: z.string().describe("Template name"),
      type: z.enum(["invoice", "credit_note", "payment_plan", "estimate", "subscription", "transaction", "statement", "chasing", "sign_in_link"]).describe("Template type"),
      subject: z.string().describe("Email subject line (supports template variables)"),
      body: z.string().describe("Email body content (HTML, supports template variables)"),
      language: z.string().describe("Template language code (e.g., 'en', 'es', 'fr')").optional(),
      template_engine: z.enum(["mustache", "liquid"]).describe("Template engine to use").optional(),
      options: templateOptionsSchema.optional(),
    }),
  },
  {
    name: "update_email_template",
    description: "Update an existing email template",
    inputSchema: z.object({
      template_id: z.string().describe("Email template ID"),
      name: z.string().describe("Template name").optional(),
      type: z.enum(["invoice", "credit_note", "payment_plan", "estimate", "subscription", "transaction", "statement", "chasing", "sign_in_link"]).describe("Template type").optional(),
      subject: z.string().describe("Email subject line (supports template variables)").optional(),
      body: z.string().describe("Email body content (HTML, supports template variables)").optional(),
      language: z.string().describe("Template language code").optional(),
      template_engine: z.enum(["mustache", "liquid"]).describe("Template engine to use").optional(),
      options: templateOptionsSchema.optional(),
    }),
  },
  {
    name: "delete_email_template",
    description: "Delete an email template",
    inputSchema: z.object({
      template_id: z.string().describe("Email template ID to delete"),
    }),
  },
];
