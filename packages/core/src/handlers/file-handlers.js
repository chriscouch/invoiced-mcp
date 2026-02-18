export const fileHandlers = {
  async list_files(invoiced, args) {
    const result = await invoiced.listFiles(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_file(invoiced, args) {
    const result = await invoiced.getFile(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_file(invoiced, args) {
    const result = await invoiced.createFile(args);
    return {
      content: [
        {
          type: "text",
          text: `File created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_file(invoiced, args) {
    const result = await invoiced.deleteFile(args.id);
    return {
      content: [
        {
          type: "text",
          text: `File deleted successfully: ${args.id}`,
        },
      ],
    };
  },
};