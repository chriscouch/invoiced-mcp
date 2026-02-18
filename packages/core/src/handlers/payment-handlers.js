export const paymentHandlers = {
  async list_payments(invoiced, args) {
    const result = await invoiced.listPayments(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_payment(invoiced, args) {
    const result = await invoiced.getPayment(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_payment(invoiced, args) {
    // Ensure customer and amount are numbers, not strings
    const data = {
      ...args,
      customer: typeof args.customer === 'string' ? parseInt(args.customer, 10) : args.customer,
      amount: typeof args.amount === 'string' ? parseFloat(args.amount) : args.amount,
    };
    
    const result = await invoiced.createPayment(data);
    return {
      content: [
        {
          type: "text",
          text: `Payment created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },
};