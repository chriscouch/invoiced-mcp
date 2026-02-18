export const customerHandlers = {
  async list_customers(invoiced, args) {
    const result = await invoiced.listCustomers(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_customer(invoiced, args) {
    const result = await invoiced.getCustomer(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_customer(invoiced, args) {
    const result = await invoiced.createCustomer(args);
    return {
      content: [
        {
          type: "text",
          text: `Customer created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_customer(invoiced, args) {
    const result = await invoiced.updateCustomer(args.id, args);
    return {
      content: [
        {
          type: "text",
          text: `Customer updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_customer(invoiced, args) {
    const result = await invoiced.deleteCustomer(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Customer deleted successfully`,
        },
      ],
    };
  },
};