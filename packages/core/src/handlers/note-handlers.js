export const noteHandlers = {
  async list_notes(invoiced, args) {
    const result = await invoiced.listNotes(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_note(invoiced, args) {
    const result = await invoiced.getNote(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_note(invoiced, args) {
    const result = await invoiced.createNote(args);
    return {
      content: [
        {
          type: "text",
          text: `Note created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_note(invoiced, args) {
    const { id, ...updateData } = args;
    const result = await invoiced.updateNote(id, updateData);
    return {
      content: [
        {
          type: "text",
          text: `Note updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_note(invoiced, args) {
    const result = await invoiced.deleteNote(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Note deleted successfully: ${args.id}`,
        },
      ],
    };
  },
};