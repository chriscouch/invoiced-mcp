export const creditNoteHandlers = {
  async list_credit_notes(invoiced, args) {
    const result = await invoiced.listCreditNotes(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },  async get_credit_note(invoiced, args) {
    const result = await invoiced.getCreditNote(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_credit_note(invoiced, args) {
    const result = await invoiced.createCreditNote(args);
    return {
      content: [
        {
          type: "text",
          text: `Credit note created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async send_credit_note(invoiced, args) {
    const result = await invoiced.sendCreditNote(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Credit note sent successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async void_credit_note(invoiced, args) {
    const result = await invoiced.voidCreditNote(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Credit note voided successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },
};