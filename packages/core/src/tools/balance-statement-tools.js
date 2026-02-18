import { z } from "zod";

export const balanceStatementTools = [
  {
    name: "get_customer_balance",
    description: "Get customer balance showing total outstanding, available credits, etc.",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID (system-generated ID)"),
      currency: z.string().describe("Optional: Currency code to filter balance by").optional(),
    }),
  },
  {
    name: "send_statement",
    description: "Send customer statement via email",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID (system-generated ID)"),
      type: z.enum(["balance_forward", "open_item"]).describe("Statement type").optional(),
      start: z.number().describe("Statement start date (Unix timestamp)").optional(),
      end: z.number().describe("Statement end date (Unix timestamp)").optional(),
      items: z.enum(["outstanding", "all"]).describe("Items to include").optional(),
      to: z.array(z.string()).describe("Email addresses to send statement to").optional(),
      bcc: z.string().describe("BCC email address").optional(),
      subject: z.string().describe("Email subject").optional(),
      message: z.string().describe("Email message body").optional(),
      attach_pdf: z.boolean().describe("Attach PDF to email").optional(),
    }),
  },
  {
    name: "send_statement_sms",
    description: "Send customer statement via SMS",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID (system-generated ID)"),
      to: z.array(z.string()).describe("Phone numbers to send SMS to"),
      type: z.enum(["balance_forward", "open_item"]).describe("Statement type").optional(),
      message: z.string().describe("SMS message text").optional(),
    }),
  },
  {
    name: "send_statement_letter",
    description: "Send customer statement via physical mail",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID (system-generated ID)"),
      type: z.enum(["balance_forward", "open_item"]).describe("Statement type").optional(),
      start: z.number().describe("Statement start date (Unix timestamp)").optional(),
      end: z.number().describe("Statement end date (Unix timestamp)").optional(),
    }),
  },
  {
    name: "generate_customer_statement",
    description: "Generate a customer statement PDF",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID (system-generated ID)"),
      type: z.enum(["balance_forward", "open_item"]).describe("Statement type").optional(),
      start: z.number().describe("Statement start date (Unix timestamp)").optional(),
      end: z.number().describe("Statement end date (Unix timestamp)").optional(),
      items: z.enum(["outstanding", "all"]).describe("Items to include").optional(),
    }),
  },
];
