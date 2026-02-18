export const contactHandlers = {
  async list_contacts(invoiced, args) {
    const result = await invoiced.listContacts(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_contact(invoiced, args) {
    const result = await invoiced.getContact(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_contact(invoiced, args) {
    const result = await invoiced.createContact(args);
    return {
      content: [
        {
          type: "text",
          text: `Contact created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_contact(invoiced, args) {
    const result = await invoiced.updateContact(args);
    return {
      content: [
        {
          type: "text",
          text: `Contact updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_contact(invoiced, args) {
    await invoiced.deleteContact(args);
    return {
      content: [
        {
          type: "text",
          text: `Contact deleted successfully`,
        },
      ],
    };
  },
};