import { z } from "zod";

export const companyTools = [
  {
    name: "get_company",
    description: "Get company settings and information",
    inputSchema: z.object({}),
  },
  {
    name: "update_company",
    description: "Update company settings",
    inputSchema: z.object({
      name: z.string().describe("Company name").optional(),
      email: z.string().describe("Company email").optional(),
      phone: z.string().describe("Company phone").optional(),
      address1: z.string().describe("Address line 1").optional(),
      address2: z.string().describe("Address line 2").optional(),
      city: z.string().describe("City").optional(),
      state: z.string().describe("State/Province").optional(),
      postal_code: z.string().describe("Postal code").optional(),
      country: z.string().describe("Country code").optional(),
      tax_id: z.string().describe("Tax ID").optional(),
      currency: z.string().describe("Default currency").optional(),
      timezone: z.string().describe("Timezone").optional(),
    }),
  },
  {
    name: "list_members",
    description: "List all company members/users",
    inputSchema: z.object({
      sort: z.string().describe("Optional: Column to sort by (e.g., 'created_at desc')").optional(),
    }),
  },
  {
    name: "get_member",
    description: "Get a specific member by ID",
    inputSchema: z.object({
      id: z.string().describe("Member ID"),
    }),
  },
  {
    name: "create_member",
    description: "Create a new member/user",
    inputSchema: z.object({
      email: z.string().describe("Member email address"),
      first_name: z.string().describe("First name").optional(),
      last_name: z.string().describe("Last name").optional(),
      role: z.string().describe("Role ID or name").optional(),
      permissions: z.array(z.string()).describe("Array of permission strings").optional(),
    }),
  },
  {
    name: "update_member",
    description: "Update an existing member",
    inputSchema: z.object({
      id: z.string().describe("Member ID"),
      first_name: z.string().describe("First name").optional(),
      last_name: z.string().describe("Last name").optional(),
      role: z.string().describe("Role ID or name").optional(),
      permissions: z.array(z.string()).describe("Array of permission strings").optional(),
    }),
  },
  {
    name: "delete_member",
    description: "Delete a member",
    inputSchema: z.object({
      id: z.string().describe("Member ID"),
    }),
  },
  {
    name: "list_roles",
    description: "List all roles",
    inputSchema: z.object({}),
  },
  {
    name: "get_role",
    description: "Get a specific role by ID",
    inputSchema: z.object({
      id: z.string().describe("Role ID"),
    }),
  },
  {
    name: "create_role",
    description: "Create a new role",
    inputSchema: z.object({
      name: z.string().describe("Role name"),
      permissions: z.array(z.string()).describe("Array of permission strings"),
    }),
  },
  {
    name: "update_role",
    description: "Update an existing role",
    inputSchema: z.object({
      id: z.string().describe("Role ID"),
      name: z.string().describe("Role name").optional(),
      permissions: z.array(z.string()).describe("Array of permission strings").optional(),
    }),
  },
  {
    name: "delete_role",
    description: "Delete a role",
    inputSchema: z.object({
      id: z.string().describe("Role ID"),
    }),
  },
  {
    name: "list_api_keys",
    description: "List all API keys",
    inputSchema: z.object({}),
  },
  {
    name: "create_api_key",
    description: "Create a new API key",
    inputSchema: z.object({
      name: z.string().describe("API key name/description"),
    }),
  },
  {
    name: "delete_api_key",
    description: "Delete an API key",
    inputSchema: z.object({
      id: z.string().describe("API key ID"),
    }),
  },
];
