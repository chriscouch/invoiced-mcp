export const reportHandlers = {
  async generate_report(invoiced, args) {
    const result = await invoiced.generateReport(args);
    return {
      content: [
        {
          type: "text",
          text: `Report created:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async get_report(invoiced, args) {
    const result = await invoiced.getReport(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async list_scheduled_reports(invoiced, args) {
    const result = await invoiced.listScheduledReports();
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_scheduled_report(invoiced, args) {
    const result = await invoiced.createScheduledReport(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async update_scheduled_report(invoiced, args) {
    const { id, ...updateData } = args;
    const result = await invoiced.updateScheduledReport(id, updateData);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async delete_scheduled_report(invoiced, args) {
    const result = await invoiced.deleteScheduledReport(args.id);
    return {
      content: [
        {
          type: "text",
          text: result ? "Scheduled report deleted successfully" : "Failed to delete scheduled report",
        },
      ],
    };
  },
};