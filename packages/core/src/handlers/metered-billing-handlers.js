export const meteredBillingHandlers = {
  async list_metered_billings(invoiced, args) {
    const result = await invoiced.listMeteredBillings(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_metered_billing(invoiced, args) {
    const result = await invoiced.createMeteredBilling(args);
    return {
      content: [
        {
          type: "text",
          text: `Metered billing record created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },
};