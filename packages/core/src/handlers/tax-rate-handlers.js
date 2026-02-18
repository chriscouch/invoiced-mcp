export const taxRateHandlers = {
  async list_tax_rates(invoiced, args) {
    const result = await invoiced.listTaxRates(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_tax_rate(invoiced, args) {
    const result = await invoiced.getTaxRate(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_tax_rate(invoiced, args) {
    const result = await invoiced.createTaxRate(args);
    return {
      content: [
        {
          type: "text",
          text: `Tax rate created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_tax_rate(invoiced, args) {
    const result = await invoiced.updateTaxRate(args.id, args);
    return {
      content: [
        {
          type: "text",
          text: `Tax rate updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_tax_rate(invoiced, args) {
    await invoiced.deleteTaxRate(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Tax rate deleted successfully`,
        },
      ],
    };
  },
};