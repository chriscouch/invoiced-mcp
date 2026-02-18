import { z } from "zod";

export const itemTools = [
  {
    name: "list_items",
    description: "List catalog items with optional filters",
    inputSchema: z.object({
      limit: z.number().describe("Number of items to return (optional)").optional(),
      sort: z.string().describe("Optional: Column to sort by (e.g., 'name asc', 'created_at desc')").optional(),
      filter: z.object({
        type: z.enum(["product", "service"]).describe("Filter by item type").optional(),
        name: z.string().describe("Filter by item name").optional(),
      }).describe("Optional: Filter object to search items by specific criteria").optional(),
      metadata: z.record(z.any()).describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_item",
    description: "Get a specific catalog item by ID",
    inputSchema: z.object({
      id: z.string().describe("Item ID"),
    }),
  },
  {
    name: "create_item",
    description: "Create a new catalog item",
    inputSchema: z.object({
      name: z.string().describe("Item name"),
      description: z.string().describe("Item description").optional(),
      type: z.enum(["product", "service"]).describe("Item type"),
      unit_cost: z.number().describe("Unit cost/price").optional(),
      currency: z.string().describe("3-letter currency code (e.g., USD)").optional(),
    }),
  },
  {
    name: "update_item",
    description: "Update a catalog item",
    inputSchema: z.object({
      id: z.string().describe("Item ID"),
      name: z.string().describe("Item name").optional(),
      description: z.string().describe("Item description").optional(),
      unit_cost: z.number().describe("Unit cost/price").optional(),
    }),
  },
  {
    name: "delete_item",
    description: "Delete a catalog item",
    inputSchema: z.object({
      id: z.string().describe("Item ID"),
    }),
  },
];
