export const balanceStatementHandlers = {
  async get_customer_balance(invoiced, args) {
    const { customer_id, currency } = args;
    const result = await invoiced.getCustomerBalance(customer_id, currency);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async send_statement(invoiced, args) {
    const { customer_id, ...statementData } = args;
    const result = await invoiced.sendStatement(customer_id, statementData);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async send_statement_sms(invoiced, args) {
    const { customer_id, ...smsData } = args;
    const result = await invoiced.sendStatementSMS(customer_id, smsData);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async send_statement_letter(invoiced, args) {
    const { customer_id, ...letterData } = args;
    const result = await invoiced.sendStatementLetter(customer_id, letterData);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async generate_customer_statement(invoiced, args) {
    const { customer_id, ...statementParams } = args;
    const result = await invoiced.generateStatement(customer_id, statementParams);
    return {
      content: [
        {
          type: "text",
          text: `Statement generated: ${result.url}`,
        },
      ],
    };
  },
};