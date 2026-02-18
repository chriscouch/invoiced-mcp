export const paymentLinkHandlers = {
  async list_payment_links(invoiced, args) {
    const result = await invoiced.listPaymentLinks(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_payment_link(invoiced, args) {
    const result = await invoiced.getPaymentLink(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_payment_link(invoiced, args) {
    const result = await invoiced.createPaymentLink(args);
    return {
      content: [
        {
          type: "text",
          text: `Payment link created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_payment_link(invoiced, args) {
    const { id, ...updateData } = args;
    const result = await invoiced.updatePaymentLink(id, updateData);
    return {
      content: [
        {
          type: "text",
          text: `Payment link updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_payment_link(invoiced, args) {
    await invoiced.deletePaymentLink(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Payment link deleted successfully`,
        },
      ],
    };
  },

  async list_payment_link_sessions(invoiced, args) {
    return await invoiced.listPaymentLinkSessions(args.payment_link_id, args);
  },
};