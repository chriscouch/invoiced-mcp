export const estimateHandlers = {
  async list_estimates(invoiced, args) {
    const result = await invoiced.listEstimates(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_estimate(invoiced, args) {
    const result = await invoiced.getEstimate(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_estimate(invoiced, args) {
    const result = await invoiced.createEstimate(args);
    return {
      content: [
        {
          type: "text",
          text: `Estimate created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_estimate(invoiced, args) {
    const result = await invoiced.updateEstimate(args.id, args);
    return {
      content: [
        {
          type: "text",
          text: `Estimate updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async send_estimate(invoiced, args) {
    const result = await invoiced.sendEstimate(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Estimate sent successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async void_estimate(invoiced, args) {
    const result = await invoiced.voidEstimate(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Estimate voided successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },
};