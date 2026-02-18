export const webhookHandlers = {
  async list_webhooks(invoiced, args) {
    const result = await invoiced.listWebhooks(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_webhook(invoiced, args) {
    const result = await invoiced.getWebhook(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_webhook(invoiced, args) {
    const result = await invoiced.createWebhook(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async update_webhook(invoiced, args) {
    const { id, ...updateData } = args;
    const result = await invoiced.updateWebhook(id, updateData);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async delete_webhook(invoiced, args) {
    const result = await invoiced.deleteWebhook(args.id);
    return {
      content: [
        {
          type: "text",
          text: result ? "Webhook deleted successfully" : "Failed to delete webhook",
        },
      ],
    };
  },

  async list_webhook_attempts(invoiced, args) {
    const { id, ...params } = args;
    const result = await invoiced.listWebhookAttempts(id, params);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async retry_webhook(invoiced, args) {
    const result = await invoiced.retryWebhook(args.id, args.attempt_id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },
};