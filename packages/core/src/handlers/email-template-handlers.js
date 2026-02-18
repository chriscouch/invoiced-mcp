export const emailTemplateHandlers = {
  async list_email_templates(invoiced, args) {
    const result = await invoiced.listEmailTemplates(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_email_template(invoiced, args) {
    const result = await invoiced.getEmailTemplate(args.template_id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_email_template(invoiced, args) {
    const { template_id, ...templateData } = args;
    const result = await invoiced.createEmailTemplate(templateData);
    return {
      content: [
        {
          type: "text",
          text: `Email template created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_email_template(invoiced, args) {
    const { template_id, ...templateData } = args;
    const result = await invoiced.updateEmailTemplate(template_id, templateData);
    return {
      content: [
        {
          type: "text",
          text: `Email template updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_email_template(invoiced, args) {
    await invoiced.deleteEmailTemplate(args.template_id);
    return {
      content: [
        {
          type: "text",
          text: `Email template ${args.template_id} deleted successfully`,
        },
      ],
    };
  },
};