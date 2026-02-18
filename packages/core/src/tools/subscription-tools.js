import { z } from "zod";

const addonSchema = z.object({
  plan: z.string().describe("Addon plan ID"),
  quantity: z.number().describe("Addon quantity").optional(),
});

const discountSchema = z.object({
  coupon: z.string().describe("Coupon code"),
});

export const subscriptionTools = [
  {
    name: "list_subscriptions",
    description: "List subscriptions with optional filters",
    inputSchema: z.object({
      limit: z.number().describe("Number of subscriptions to return (optional)").optional(),
      sort: z.string().describe("Optional: Column to sort by (e.g., 'created_at desc')").optional(),
      filter: z.object({
        customer: z.string().describe("Filter by customer ID").optional(),
        plan: z.string().describe("Filter by plan ID").optional(),
        status: z.enum(["active", "past_due", "canceled", "unpaid"]).describe("Filter by subscription status").optional(),
      }).describe("Optional: Filter object to search subscriptions by specific criteria").optional(),
      metadata: z.record(z.any()).describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_subscription",
    description: "Get a specific subscription by ID",
    inputSchema: z.object({
      id: z.string().describe("Subscription ID"),
    }),
  },
  {
    name: "create_subscription",
    description: "Create a new subscription",
    inputSchema: z.object({
      customer: z.number().describe("Customer ID (the system-generated ID, not customer number)"),
      plan: z.string().describe("Plan ID"),
      quantity: z.number().describe("Quantity of plan units").optional(),
      start_date: z.string().describe("Subscription start date (YYYY-MM-DD)").optional(),
      cycles: z.number().describe("Number of billing cycles (omit for infinite)").optional(),
      addons: z.array(addonSchema).describe("Subscription addons").optional(),
      discounts: z.array(discountSchema).describe("Subscription discounts").optional(),
      contract_period_start_date: z.string().describe("Contract period start date (YYYY-MM-DD)").optional(),
      contract_period_end_date: z.string().describe("Contract period end date (YYYY-MM-DD)").optional(),
      cancel_at_period_end: z.boolean().describe("Cancel at end of current period").optional(),
      metadata: z.record(z.any()).describe("Custom metadata").optional(),
    }),
  },
  {
    name: "preview_subscription",
    description: "Preview a subscription without creating it",
    inputSchema: z.object({
      customer: z.number().describe("Customer ID (the system-generated ID, not customer number)"),
      plan: z.string().describe("Plan ID"),
      quantity: z.number().describe("Quantity of plan units").optional(),
      start_date: z.string().describe("Subscription start date (YYYY-MM-DD)").optional(),
      cycles: z.number().describe("Number of billing cycles").optional(),
      addons: z.array(addonSchema).describe("Subscription addons").optional(),
      discounts: z.array(discountSchema).describe("Subscription discounts").optional(),
    }),
  },
  {
    name: "update_subscription",
    description: "Update a subscription",
    inputSchema: z.object({
      id: z.string().describe("Subscription ID"),
      plan: z.string().describe("New plan ID").optional(),
      quantity: z.number().describe("New quantity").optional(),
      cycles: z.number().describe("Number of billing cycles").optional(),
      addons: z.array(addonSchema).describe("Updated addons").optional(),
      discounts: z.array(discountSchema).describe("Updated discounts").optional(),
      cancel_at_period_end: z.boolean().describe("Cancel at end of period").optional(),
      metadata: z.record(z.any()).describe("Custom metadata").optional(),
    }),
  },
  {
    name: "cancel_subscription",
    description: "Cancel a subscription",
    inputSchema: z.object({
      id: z.number().describe("Subscription ID"),
      canceled_at: z.string().describe("Cancellation date (YYYY-MM-DD)").optional(),
      prorate: z.boolean().describe("Whether to prorate final invoice").optional(),
    }),
  },
  {
    name: "cancel_subscription_alt",
    description: "Alternative subscription cancellation using update method",
    inputSchema: z.object({
      id: z.number().describe("Subscription ID"),
      canceled_at: z.string().describe("Cancellation date (YYYY-MM-DD)").optional(),
    }),
  },
];
