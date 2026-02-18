import { z } from "zod";

const paymentLinkItemSchema = z.object({
  name: z.string().describe("Item name"),
  description: z.string().optional().describe("Item description"),
  quantity: z.number().describe("Item quantity"),
  unit_cost: z.number().describe("Unit cost in cents"),
  taxable: z.boolean().optional().describe("Whether item is taxable"),
});

const paymentLinkFieldSchema = z.object({
  name: z.string().describe("Field name"),
  label: z.string().describe("Field label"),
  type: z.enum(["text", "textarea", "number", "email", "phone", "date", "select", "checkbox"]).describe("Field type"),
  required: z.boolean().optional().describe("Whether field is required"),
  options: z.array(z.string()).optional().describe("Options for select fields"),
});

export const paymentLinkTools = [
  {
    name: "list_payment_links",
    description: "List payment links with optional filters",
    inputSchema: z.object({
      limit: z.number().optional().describe("Number of payment links to return (optional)"),
      sort: z.string().optional().describe("Optional: Column to sort by (e.g., 'created_at desc')"),
      filter: z.object({
        customer: z.string().optional().describe("Filter by customer ID"),
        status: z.enum(["active", "completed", "deleted"]).optional().describe("Filter by payment link status"),
      }).optional().describe("Optional: Filter object to search payment links by specific criteria"),
      metadata: z.object({}).passthrough().optional().describe("Optional: Metadata filter object for custom field filtering"),
      updated_after: z.number().optional().describe("Optional: Unix timestamp - only gets records updated after this time"),
    }),
  },
  {
    name: "get_payment_link",
    description: "Get a specific payment link by ID",
    inputSchema: z.object({
      id: z.string().describe("Payment link ID"),
    }),
  },
  {
    name: "create_payment_link",
    description: "Create a new payment link",
    inputSchema: z.object({
      name: z.string().optional().describe("Payment link name"),
      customer: z.string().optional().describe("Customer ID (optional for reusable links)"),
      currency: z.string().optional().describe("3-letter currency code (e.g., USD)"),
      reusable: z.boolean().optional().describe("Whether the payment link can be used multiple times"),
      collect_billing_address: z.boolean().optional().describe("Whether to collect billing address"),
      collect_shipping_address: z.boolean().optional().describe("Whether to collect shipping address"),
      collect_phone_number: z.boolean().optional().describe("Whether to collect phone number"),
      terms_of_service_url: z.string().optional().describe("URL to terms of service"),
      after_completion_url: z.string().optional().describe("URL to redirect to after payment completion"),
      items: z.array(paymentLinkItemSchema).optional().describe("Payment link items (optional - leave empty for customer to specify amounts)"),
      fields: z.array(paymentLinkFieldSchema).optional().describe("Custom fields to collect (optional - leave empty if no custom fields needed)"),
    }),
  },
  {
    name: "update_payment_link",
    description: "Update a payment link",
    inputSchema: z.object({
      id: z.string().describe("Payment link ID"),
      name: z.string().optional().describe("Payment link name"),
      customer: z.number().optional().describe("Customer ID (the system-generated ID, not customer number)"),
      currency: z.string().optional().describe("3-letter currency code"),
      reusable: z.boolean().optional().describe("Whether the payment link can be used multiple times"),
      collect_billing_address: z.boolean().optional().describe("Whether to collect billing address"),
      collect_shipping_address: z.boolean().optional().describe("Whether to collect shipping address"),
      collect_phone_number: z.boolean().optional().describe("Whether to collect phone number"),
      terms_of_service_url: z.string().optional().describe("URL to terms of service"),
      after_completion_url: z.string().optional().describe("URL to redirect to after payment completion"),
      items: z.array(paymentLinkItemSchema).optional().describe("Payment link items"),
      fields: z.array(paymentLinkFieldSchema).optional().describe("Custom fields to collect"),
    }),
  },
  {
    name: "delete_payment_link",
    description: "Delete a payment link",
    inputSchema: z.object({
      id: z.string().describe("Payment link ID"),
    }),
  },
  {
    name: "list_payment_link_sessions",
    description: "List sessions for a payment link",
    inputSchema: z.object({
      payment_link_id: z.string().describe("Payment link ID"),
      limit: z.number().optional().describe("Number of sessions to return (optional)"),
    }),
  },
];
