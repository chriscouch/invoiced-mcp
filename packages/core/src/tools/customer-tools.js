import { z } from "zod";

const addressSchema = z.object({
  line1: z.string().optional(),
  line2: z.string().optional(),
  city: z.string().optional(),
  state: z.string().optional(),
  postal_code: z.string().optional(),
  country: z.string().optional(),
}).optional();

export const customerTools = [
  {
    name: "list_customers",
    description: "List all customers. All parameters are optional - can be called without any parameters to get all customers.",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'name asc', 'created_at desc')").optional(),
      filter: z.object({
        email: z.string().describe("Filter by customer email").optional(),
        name: z.string().describe("Filter by customer name (matches partial names)").optional(),
        number: z.string().describe("Filter by customer number (your custom identifier)").optional(),
      }).describe("Optional: Filter object to search customers by specific criteria").optional(),
      metadata: z.record(z.any()).describe("Optional: Metadata filter object for custom field filtering").optional(),
      payment_source: z.boolean().describe("Optional: When true, only returns customers with a payment source; when false, only those without").optional(),
      open_balance: z.boolean().describe("Optional: When true, only returns customers with an open balance; when false, only those without").optional(),
      updated_after: z.number().describe("Optional: Unix timestamp - only gets records updated after this time").optional(),
    }),
  },
  {
    name: "get_customer",
    description: "Get a specific customer by ID",
    inputSchema: z.object({
      id: z.string().describe("Customer ID (the system-generated ID, not your custom number)"),
    }),
  },
  {
    name: "create_customer",
    description: "Create a new customer",
    inputSchema: z.object({
      name: z.string().describe("Customer name"),
      number: z.string().describe("Your custom identifier for this customer (e.g., 'CUST-001')").optional(),
      email: z.string().describe("Customer email address").optional(),
      phone: z.string().describe("Customer phone number").optional(),
      address: addressSchema.describe("Customer address"),
      company: z.string().describe("Company name").optional(),
      website: z.string().describe("Customer website URL").optional(),
      notes: z.string().describe("Notes about the customer").optional(),
      payment_terms: z.string().describe("Payment terms (e.g., 'NET 30')").optional(),
      language: z.string().describe("Customer's preferred language code").optional(),
      currency: z.string().describe("Customer's preferred currency (3-letter code)").optional(),
    }),
  },
  {
    name: "update_customer",
    description: "Update an existing customer",
    inputSchema: z.object({
      id: z.string().describe("Customer ID (the system-generated ID, not your custom number)"),
      name: z.string().describe("Customer name").optional(),
      number: z.string().describe("Your custom identifier for this customer (e.g., 'CUST-001')").optional(),
      email: z.string().describe("Customer email address").optional(),
      phone: z.string().describe("Customer phone number").optional(),
      address: addressSchema.describe("Customer address"),
      company: z.string().describe("Company name").optional(),
      website: z.string().describe("Customer website URL").optional(),
      notes: z.string().describe("Notes about the customer").optional(),
      payment_terms: z.string().describe("Payment terms (e.g., 'NET 30')").optional(),
      language: z.string().describe("Customer's preferred language code").optional(),
      currency: z.string().describe("Customer's preferred currency (3-letter code)").optional(),
    }),
  },
  {
    name: "delete_customer",
    description: "Delete a customer",
    inputSchema: z.object({
      id: z.string().describe("Customer ID (the system-generated ID, not your custom number)"),
    }),
  },
];
