export const planHandlers = {
  async list_plans(invoiced, args) {
    const result = await invoiced.listPlans(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_plan(invoiced, args) {
    const result = await invoiced.getPlan(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_plan(invoiced, args) {
    const result = await invoiced.createPlan(args);
    return {
      content: [
        {
          type: "text",
          text: `Plan created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_plan(invoiced, args) {
    const result = await invoiced.updatePlan(args.id, args);
    return {
      content: [
        {
          type: "text",
          text: `Plan updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_plan(invoiced, args) {
    await invoiced.deletePlan(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Plan deleted successfully`,
        },
      ],
    };
  },
};