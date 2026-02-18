import { z } from "zod";

export const fileTools = [
  {
    name: "get_file",
    description: "Get a specific file by ID",
    inputSchema: z.object({
      id: z.string().describe("File ID"),
    }),
  },
  {
    name: "create_file",
    description: "Create/upload a new file",
    inputSchema: z.object({
      name: z.string().describe("File name"),
      type: z.string().describe("File MIME type (e.g., 'application/pdf', 'image/png')"),
      content: z.string().describe("File content (base64 encoded for binary files)").optional(),
      url: z.string().describe("URL to file content (alternative to content field)").optional(),
      size: z.number().describe("File size in bytes").optional(),
      metadata: z.object({}).describe("Optional metadata for the file").optional(),
    }),
  },
  {
    name: "delete_file",
    description: "Delete a file",
    inputSchema: z.object({
      id: z.string().describe("File ID to delete"),
    }),
  },
];
