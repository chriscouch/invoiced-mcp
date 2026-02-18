export const itemHandlers = {
  async list_items(invoiced, args) {
    const result = await invoiced.listItems(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_item(invoiced, args) {
    const result = await invoiced.getItem(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_item(invoiced, args) {
    const result = await invoiced.createItem(args);
    return {
      content: [
        {
          type: "text",
          text: `Item created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_item(invoiced, args) {
    const result = await invoiced.updateItem(args.id, args);
    return {
      content: [
        {
          type: "text",
          text: `Item updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_item(invoiced, args) {
    await invoiced.deleteItem(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Item deleted successfully`,
        },
      ],
    };
  },
};