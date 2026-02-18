export const refundHandlers = {
  async list_refunds(invoiced, args) {
    const result = await invoiced.listRefunds(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_refund(invoiced, args) {
    const result = await invoiced.getRefund(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_refund(invoiced, args) {
    const result = await invoiced.createRefund(args);
    return {
      content: [
        {
          type: "text",
          text: `Refund created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },
};