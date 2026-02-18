import { z } from "zod";

export const couponTools = [
  {
    name: "list_coupons",
    description: "List all coupons. All parameters are optional - can be called without any parameters to get all coupons.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'created_at desc', 'name asc')").optional(),
      filter: z.object({
        is_percent: z.boolean().describe("Filter by percentage vs fixed amount coupons").optional(),
        exclusive: z.boolean().describe("Filter by exclusive coupons only").optional(),
        expired: z.boolean().describe("Filter expired coupons").optional(),
      }).describe("Optional: Filter object to search coupons by specific criteria").optional(),
      metadata: z.object({}).passthrough().describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_coupon",
    description: "Get a specific coupon by ID",
    inputSchema: z.object({
      id: z.string().describe("Coupon ID"),
    }),
  },
  {
    name: "create_coupon",
    description: "Create a new coupon",
    inputSchema: z.object({
      id: z.string().describe("Coupon code/ID (must be unique)"),
      name: z.string().describe("Coupon name/description"),
      value: z.number().describe("Coupon value (amount or percentage based on is_percent)"),
      is_percent: z.boolean().describe("Whether the coupon value is a percentage (true) or fixed amount (false)").optional(),
      currency: z.string().describe("3-letter currency code (e.g., USD) - required for fixed amount coupons").optional(),
      exclusive: z.boolean().describe("Whether this coupon cannot be combined with others").optional(),
      expiration_date: z.string().describe("Optional expiration date (YYYY-MM-DD)").optional(),
      max_redemptions: z.number().describe("Maximum number of times this coupon can be used").optional(),
      metadata: z.object({}).passthrough().describe("Optional metadata for the coupon").optional(),
    }),
  },
  {
    name: "update_coupon",
    description: "Update an existing coupon",
    inputSchema: z.object({
      id: z.string().describe("Coupon ID"),
      name: z.string().describe("Coupon name/description").optional(),
      value: z.number().describe("Coupon value (amount or percentage based on is_percent)").optional(),
      is_percent: z.boolean().describe("Whether the coupon value is a percentage (true) or fixed amount (false)").optional(),
      currency: z.string().describe("3-letter currency code (e.g., USD) - required for fixed amount coupons").optional(),
      exclusive: z.boolean().describe("Whether this coupon cannot be combined with others").optional(),
      expiration_date: z.string().describe("Expiration date (YYYY-MM-DD)").optional(),
      max_redemptions: z.number().describe("Maximum number of times this coupon can be used").optional(),
      metadata: z.object({}).passthrough().describe("Optional metadata for the coupon").optional(),
    }),
  },
  {
    name: "delete_coupon",
    description: "Delete a coupon",
    inputSchema: z.object({
      id: z.string().describe("Coupon ID to delete"),
    }),
  },
];
