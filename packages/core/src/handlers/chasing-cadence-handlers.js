export const chasingCadenceHandlers = {
  async list_chasing_cadences(invoiced, args) {
    const result = await invoiced.listChasingCadences(args);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },
  
  async get_chasing_cadence(invoiced, args) {
    const result = await invoiced.getChasingCadence(args.id);
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  },

  async create_chasing_cadence(invoiced, args) {
    // Debug logging to see what's being received
    console.error('[DEBUG] create_chasing_cadence called with args:', JSON.stringify(args, null, 2));
    
    // Transform the schedule format and validate steps
    const transformedArgs = { ...args };
    
    // Standard Invoiced email template IDs that should exist by default
    // Custom templates must be created first using create_email_template
    const defaultTemplates = [
      'new_invoice_email',
      'unpaid_invoice_email', 
      'late_payment_reminder_email',
      'paid_invoice_email',
      'payment_plan_onboard_email',
      'payment_receipt_email',
      'refund_email',
      'estimate_email',
      'credit_note_email',
      'statement_email',
      'auto_payment_failed_email',
      'subscription_confirmation_email',
      'subscription_canceled_email',
      'subscription_renews_soon_email',
      'sign_in_link_email'
    ];
    
    if (transformedArgs.steps && Array.isArray(transformedArgs.steps)) {
      transformedArgs.steps = transformedArgs.steps.map(step => {
        const transformedStep = { ...step };
        
        // Transform schedule format to Invoiced API format
        if (step.schedule) {
          // If it's in the +/- format, convert to age: or past_due_age:
          if (/^[+-]?\d+$/.test(step.schedule)) {
            const days = parseInt(step.schedule);
            if (days <= 0) {
              // Negative or zero means before or on due date
              transformedStep.schedule = `age:${Math.abs(days)}`;
            } else {
              // Positive means after due date
              transformedStep.schedule = `past_due_age:${days}`;
            }
          }
          // If it's already in the correct format, leave it as is
        }
        
        // Handle email template IDs for email actions
        if (step.action === 'email') {
          // If no template specified, use a default based on schedule
          if (!step.email_template_id) {
            const scheduleMatch = (transformedStep.schedule || '').match(/(age|past_due_age):(\d+)/);
            if (scheduleMatch) {
              if (scheduleMatch[1] === 'age') {
                transformedStep.email_template_id = 'unpaid_invoice_email';
              } else {
                transformedStep.email_template_id = 'late_payment_reminder_email';
              }
            } else {
              // Default fallback
              transformedStep.email_template_id = 'unpaid_invoice_email';
            }
          }
          // Note: email_template_id must reference an existing template
          // Use standard templates or create custom ones first with create_email_template
        }
        
        // Remove mail actions as they're not supported
        if (step.action === 'mail') {
          return null;
        }
        
        return transformedStep;
      }).filter(step => step !== null);
    }
    
    // Debug logging to see what's being sent to API
    console.error('[DEBUG] Sending to Invoiced API:', JSON.stringify(transformedArgs, null, 2));
    
    try {
      const result = await invoiced.createChasingCadence(transformedArgs);
      console.error('[DEBUG] API Response:', JSON.stringify(result, null, 2));
      return {
        content: [
          {
            type: "text",
            text: `Chasing cadence created successfully:\n${JSON.stringify(result, null, 2)}`,
          },
        ],
      };
    } catch (error) {
      console.error('[DEBUG] API Error:', error.message);
      console.error('[DEBUG] Error details:', error);
      throw error;
    }
  },

  async update_chasing_cadence(invoiced, args) {
    const { id, ...updateData } = args;
    const result = await invoiced.updateChasingCadence(id, updateData);
    return {
      content: [
        {
          type: "text",
          text: `Chasing cadence updated successfully:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async delete_chasing_cadence(invoiced, args) {
    await invoiced.deleteChasingCadence(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Chasing cadence deleted successfully`,
        },
      ],
    };
  },

  async run_chasing_cadence(invoiced, args) {
    const result = await invoiced.runChasingCadence(args.id);
    return {
      content: [
        {
          type: "text",
          text: `Chasing cadence execution started:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },

  async assign_chasing_cadence(invoiced, args) {
    const result = await invoiced.assignChasingCadence(args.id, args);
    return {
      content: [
        {
          type: "text",
          text: `Chasing cadence assignment completed:\n${JSON.stringify(result, null, 2)}`,
        },
      ],
    };
  },
};