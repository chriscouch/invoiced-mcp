import { z } from "zod";

export const contactTools = [
  {
    name: "list_contacts",
    description: "List contacts for a specific customer with optional filters",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID to list contacts for"),
      sort: z.string().describe("Sort order (optional)").optional(),
    }),
  },
  {
    name: "get_contact",
    description: "Get a specific contact by customer and contact ID",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID"),
      id: z.string().describe("Contact ID"),
    }),
  },
  {
    name: "create_contact",
    description: "Create a new contact. Sometimes called billing contact",
    inputSchema: z.object({
      customer: z.number().describe("Customer ID this contact belongs to").optional(),
      name: z.string().describe("Contact name"),
      email: z.string().describe("Contact email").optional(),
      phone: z.string().describe("Contact phone").optional(),
      primary: z.boolean().describe("When true the contact will be copied on any account communications").optional(),
    }),
  },
  {
    name: "update_contact",
    description: "Update a contact",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID"),
      id: z.string().describe("Contact ID"),
      name: z.string().describe("Contact name").optional(),
      email: z.string().describe("Contact email").optional(),
      phone: z.string().describe("Contact phone").optional(),
      primary: z.boolean().describe("Whether this is the primary contact").optional(),
    }),
  },
  {
    name: "delete_contact",
    description: "Delete a contact",
    inputSchema: z.object({
      customer_id: z.number().describe("Customer ID"),
      id: z.string().describe("Contact ID"),
    }),
  },
];
