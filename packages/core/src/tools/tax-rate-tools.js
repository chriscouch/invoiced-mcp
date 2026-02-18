import { z } from "zod";

export const taxRateTools = [
  {
    name: "list_tax_rates",
    description: "List tax rates with optional filters",
    inputSchema: z.object({
      limit: z.number().describe("Number of tax rates to return (optional)").optional(),
      sort: z.string().describe("Optional: Column to sort by (e.g., 'name asc', 'created_at desc')").optional(),
      filter: z.object({
        inclusive: z.boolean().describe("Filter by whether tax is inclusive").optional(),
        is_percent: z.boolean().describe("Filter by whether tax is percentage-based").optional(),
      }).describe("Optional: Filter object to search tax rates by specific criteria").optional(),
      metadata: z.object({}).passthrough().describe("Optional: Metadata filter object for custom field filtering").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_tax_rate",
    description: "Get a specific tax rate by ID",
    inputSchema: z.object({
      id: z.string().describe("Tax rate ID"),
    }),
  },
  {
    name: "create_tax_rate",
    description: "Create a new tax rate",
    inputSchema: z.object({
      name: z.string().describe("Tax rate name"),
      value: z.number().describe("Tax rate percentage (e.g., 8.25 for 8.25%)"),
      inclusive: z.boolean().describe("Whether tax is inclusive").optional(),
    }),
  },
  {
    name: "update_tax_rate",
    description: "Update a tax rate",
    inputSchema: z.object({
      id: z.string().describe("Tax rate ID"),
      name: z.string().describe("Tax rate name").optional(),
      value: z.number().describe("Tax rate percentage").optional(),
      inclusive: z.boolean().describe("Whether tax is inclusive").optional(),
    }),
  },
  {
    name: "delete_tax_rate",
    description: "Delete a tax rate",
    inputSchema: z.object({
      id: z.string().describe("Tax rate ID"),
    }),
  },
];
