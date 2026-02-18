export const chargeHandlers = {
  async list_charges(invoiced, args) {
    const result = await invoiced.listCharges(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_charge(invoiced, args) {
    const result = await invoiced.getCharge(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_charge(invoiced, args) {
    const result = await invoiced.createCharge(args);
    return {
      content: [
        {
          type: "text",
          text: `Charge created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },
};