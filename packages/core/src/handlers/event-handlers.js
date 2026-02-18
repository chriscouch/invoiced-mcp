export const eventHandlers = {
  async list_events(invoiced, args) {
    const result = await invoiced.listEvents(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_event(invoiced, args) {
    const result = await invoiced.getEvent(args.id);
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