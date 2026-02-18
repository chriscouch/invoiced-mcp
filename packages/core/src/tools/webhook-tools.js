import { z } from "zod";

export const webhookTools = [
  {
    name: "list_webhooks",
    description: "List all webhooks",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'created_at desc')").optional(),
    }),
  },
  {
    name: "create_webhook",
    description: "Create a new webhook",
    inputSchema: z.object({
      url: z.string().describe("URL to send webhook events to"),
      events: z.array(z.enum([
        "invoice.created", "invoice.updated", "invoice.deleted", "invoice.voided",
        "invoice.paid", "invoice.sent", "invoice.viewed", "invoice.payment_failed",
        "customer.created", "customer.updated", "customer.deleted",
        "payment.created", "payment.updated", "payment.deleted",
        "subscription.created", "subscription.updated", "subscription.canceled",
        "estimate.created", "estimate.updated", "estimate.voided",
        "credit_note.created", "credit_note.updated", "credit_note.voided"
      ])).describe("Array of event types to subscribe to"),
    }),
  },
  {
    name: "update_webhook",
    description: "Update an existing webhook",
    inputSchema: z.object({
      id: z.string().describe("Webhook ID"),
      url: z.string().describe("URL to send webhook events to").optional(),
      events: z.array(z.string()).describe("Array of event types to subscribe to").optional(),
    }),
  },
  {
    name: "delete_webhook",
    description: "Delete a webhook",
    inputSchema: z.object({
      id: z.string().describe("Webhook ID"),
    }),
  },
  {
    name: "list_webhook_attempts",
    description: "List webhook delivery attempts",
    inputSchema: z.object({
      id: z.string().describe("Webhook ID"),
      sort: z.string().describe("Optional: Column to sort by (e.g., 'created_at desc')").optional(),
    }),
  },
  {
    name: "retry_webhook",
    description: "Retry a failed webhook delivery",
    inputSchema: z.object({
      id: z.string().describe("Webhook ID"),
      attempt_id: z.string().describe("Webhook attempt ID to retry"),
    }),
  },
];
