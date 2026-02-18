export const invoiceHandlers = {
  async list_invoices(invoiced, args) {
    const result = await invoiced.listInvoices(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_invoice(invoiced, args) {
    const result = await invoiced.getInvoice(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_invoice(invoiced, args) {
    // Ensure customer is a number, not a string
    const data = {
      ...args,
      customer: typeof args.customer === 'string' ? parseInt(args.customer, 10) : args.customer,
    };
    
    const result = await invoiced.createInvoice(data);
    return {
      content: [
        {
          type: "text",
          text: `Invoice created successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async update_invoice(invoiced, args) {
    const result = await invoiced.updateInvoice(args.id, args);
    return {
      content: [
        {
          type: "text",
          text: `Invoice updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async send_invoice(invoiced, args) {
    const result = await invoiced.sendInvoice(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Invoice sent successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async void_invoice(invoiced, args) {
    const result = await invoiced.voidInvoice(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Invoice voided successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },
};