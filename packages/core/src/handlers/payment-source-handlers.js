export const paymentSourceHandlers = {
  async list_payment_sources(invoiced, args) {
    const result = await invoiced.listPaymentSources(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_payment_source(invoiced, args) {
    const result = await invoiced.getPaymentSource(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_payment_source(invoiced, args) {
    const result = await invoiced.createPaymentSource(args);
    return {
      content: [
        {
          type: "text",
          text: `Payment source created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_payment_source(invoiced, args) {
    await invoiced.deletePaymentSource(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Payment source ${args.id} deleted successfully`,
        },
      ],
    };
  },
};