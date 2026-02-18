export const subscriptionHandlers = {
  async list_subscriptions(invoiced, args) {
    const result = await invoiced.listSubscriptions(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_subscription(invoiced, args) {
    const result = await invoiced.getSubscription(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_subscription(invoiced, args) {
    const result = await invoiced.createSubscription(args);
    return {
      content: [
        {
          type: "text",
          text: `Subscription created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async preview_subscription(invoiced, args) {
    const result = await invoiced.previewSubscription(args);
    return {
      content: [
        {
          type: "text",
          text: `Subscription preview:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_subscription(invoiced, args) {
    const result = await invoiced.updateSubscription(args.id, args);
    return {
      content: [
        {
          type: "text",
          text: `Subscription updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async cancel_subscription(invoiced, args) {
    const result = await invoiced.cancelSubscription(args.id, args);
    return {
      content: [
        {
          type: "text",
          text: `Subscription canceled successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },
};