export const creditBalanceHandlers = {
  async list_credit_balances(invoiced, args) {
    const result = await invoiced.listCreditBalances(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async get_credit_balance(invoiced, args) {
    const result = await invoiced.getCreditBalance(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_credit_balance(invoiced, args) {
    console.error('[DEBUG] create_credit_balance handler received:', JSON.stringify(args, null, 2));
    console.error('[DEBUG] Types:', Object.entries(args || {}).map(([k, v]) => `${k}: ${typeof v} = ${v}`));
    
    // Clean and convert the data
    const data = {};
    
    // Handle customer - must be an integer
    if (args.customer !== undefined) {
      data.customer = parseInt(String(args.customer), 10);
      if (isNaN(data.customer)) {
        throw new Error(`Invalid customer ID: ${args.customer}`);
      }
    }
    
    // Handle amount - must be a number
    if (args.amount !== undefined) {
      data.amount = parseFloat(String(args.amount));
      if (isNaN(data.amount)) {
        throw new Error(`Invalid amount: ${args.amount}`);
      }
    }
    
    // Handle optional fields
    if (args.currency) {
      data.currency = String(args.currency).toLowerCase();
    }
    if (args.notes) {
      data.notes = String(args.notes);
    }
    
    console.error('[DEBUG] Cleaned data to send:', JSON.stringify(data, null, 2));
    
    try {
      const result = await invoiced.createCreditBalance(data);
      console.error('[DEBUG] API response:', JSON.stringify(result, null, 2));
      return {
        content: [
          {
            type: "text",
            text: `Credit balance created successfully:\n${JSON.stringify(result, null, 2)}`,
          },
        ],
      };
    } catch (error) {
      console.error('[DEBUG] API error:', error.message);
      console.error('[DEBUG] Full error:', error);
      throw error;
    }
  },
};