export const paymentPlanHandlers = {
  async list_payment_plans(invoiced, args) {
    const result = await invoiced.listPaymentPlans(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_payment_plan(invoiced, args) {
    const result = await invoiced.getPaymentPlan(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_payment_plan(invoiced, args) {
    const result = await invoiced.createPaymentPlan(args);
    return {
      content: [
        {
          type: "text",
          text: `Payment plan created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_payment_plan(invoiced, args) {
    const { id, ...updateData } = args;
    const result = await invoiced.updatePaymentPlan(id, updateData);
    return {
      content: [
        {
          type: "text",
          text: `Payment plan updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_payment_plan(invoiced, args) {
    const result = await invoiced.deletePaymentPlan(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Payment plan deleted successfully: ${args.id}`,
        },
      ],
    };
  },
};