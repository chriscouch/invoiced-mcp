/**
 * Support-focused prompts for Invoiced
 *
 * These prompts appear as slash commands in Claude Desktop
 * and guide users through common support workflows.
 */

import { z } from 'zod';

export function registerPrompts(server) {
  // Diagnose payment issues
  server.registerPrompt(
    'diagnose-payment',
    {
      title: 'Diagnose Payment Issue',
      description: 'Diagnose payment issues for an invoice',
      argsSchema: {
        invoice_id: z.string().describe('The invoice ID to diagnose')
      }
    },
    async ({ invoice_id }) => ({
      messages: [{
        role: 'user',
        content: {
          type: 'string',
          text: `Please help me diagnose payment issues for invoice ${invoice_id}. Follow these steps:

1. **Get Invoice Details**: Use get_invoice to retrieve the invoice and check:
   - Current status (draft, sent, paid, void)
   - Amount due and currency
   - Due date and if overdue
   - Payment terms

2. **Check Payment History**: Use list_payments filtered by this invoice to see:
   - Any partial payments
   - Failed payment attempts
   - Payment methods used

3. **Review Customer**: Get the customer details to check:
   - Payment sources on file
   - Credit balance available
   - Default payment method

4. **Identify Issues**: Based on the above, identify potential problems:
   - Missing payment source
   - Expired card
   - Insufficient credit balance
   - Invoice not yet sent

5. **Recommend Solutions**: Suggest specific actions to resolve the issue.`
        }
      }]
    })
  );

  // Collection strategy
  server.registerPrompt(
    'collection-strategy',
    {
      title: 'Create Collection Strategy',
      description: 'Create a collection strategy for overdue invoices',
      argsSchema: {
        customer_id: z.string().optional().describe('Optional: Focus on a specific customer'),
        min_days_overdue: z.string().optional().describe('Minimum days past due (default: 1)')
      }
    },
    async ({ customer_id, min_days_overdue = '1' }) => ({
      messages: [{
        role: 'user',
        content: [{
          type: 'text',
          text: customer_id
            ? `Create a collection strategy for customer ${customer_id}:

1. **Gather Data**:
   - Use list_invoices with status=past_due for this customer
   - Get customer details and payment history
   - Check existing chasing cadence assignments

2. **Analyze Situation**:
   - Total amount overdue
   - Age of oldest invoice
   - Payment history patterns
   - Previous collection attempts

3. **Recommend Strategy**:
   - Immediate actions (calls, emails)
   - Payment plan options if needed
   - Escalation timeline
   - Whether to pause services`

            : `Create a prioritized collection strategy for all overdue invoices:

1. **Get Overdue Invoices**: Use list_invoices with status=past_due to find all overdue invoices at least ${min_days_overdue} days past due

2. **Categorize by Age**:
   - 1-30 days: Gentle reminders
   - 31-60 days: Escalated contact
   - 61-90 days: Payment plan offers
   - 90+ days: Final notice / collections

3. **Prioritize by Amount**: Focus on high-value accounts first

4. **Create Action Plan**:
   - List top 10 accounts to contact today
   - Recommend communication templates
   - Suggest payment plan terms for large balances

5. **Review Chasing Cadences**: Check if customers are assigned to appropriate cadences`
        }]
      }]
    })
  );

  // Customer health check
  server.registerPrompt(
    'customer-health',
    {
      title: 'Customer Health Check',
      description: 'Comprehensive health check for a customer account',
      argsSchema: {
        customer_id: z.string().describe('The customer ID to analyze')
      }
    },
    async ({ customer_id }) => ({
      messages: [{
        role: 'user',
        content: [{
          type: 'text',
          text: `Perform a comprehensive health check for customer ${customer_id}:

1. **Account Overview**:
   - Get customer details with get_customer
   - Check credit balance with list_credit_balances
   - Review payment sources with list_payment_sources

2. **Invoice Analysis**:
   - List all invoices with list_invoices
   - Calculate: total invoiced, total paid, outstanding balance
   - Identify any disputes or voided invoices

3. **Payment Behavior**:
   - Review payment history with list_payments
   - Calculate average days to pay
   - Identify preferred payment methods
   - Note any failed charges

4. **Subscription Status** (if applicable):
   - Check active subscriptions with list_subscriptions
   - Review plan details and billing frequency

5. **Risk Assessment**:
   - Payment reliability score (based on history)
   - Recommended credit limit
   - Chasing cadence recommendation

6. **Summary Report**: Provide a brief health score and key recommendations.`
        }]
      }]
    })
  );

  // AR aging analysis (no arguments)
  server.registerPrompt(
    'ar-aging',
    {
      title: 'AR Aging Analysis',
      description: 'Generate and analyze accounts receivable aging report'
    },
    async () => ({
      messages: [{
        role: 'user',
        content: [{
          type: 'text',
          text: `Generate and analyze an accounts receivable aging report:

1. **Generate Report**: Use generate_report with type "AgingDetail" or "AgingSummary"

2. **Analyze by Bucket**:
   - Current (not yet due)
   - 1-30 days past due
   - 31-60 days past due
   - 61-90 days past due
   - 90+ days past due

3. **Key Metrics**:
   - Total AR outstanding
   - Percentage in each aging bucket
   - Average days sales outstanding (DSO)
   - Top 10 customers by balance

4. **Trend Analysis**:
   - Compare to typical benchmarks
   - Identify concerning patterns

5. **Recommendations**:
   - Accounts requiring immediate attention
   - Suggested collection priorities
   - Process improvements`
        }]
      }]
    })
  );

  // Invoice troubleshooting
  server.registerPrompt(
    'troubleshoot-invoice',
    {
      title: 'Troubleshoot Invoice',
      description: 'Troubleshoot invoice issues',
      argsSchema: {
        invoice_id: z.string().describe('The invoice ID with issues'),
        issue_type: z.string().optional().describe('Type of issue: sending, payment, display, calculation')
      }
    },
    async ({ invoice_id, issue_type }) => ({
      messages: [{
        role: 'user',
        content: [{
          type: 'text',
          text: `Troubleshoot issues with invoice ${invoice_id}${issue_type ? ` (reported issue: ${issue_type})` : ''}:

1. **Get Invoice Details**: Retrieve full invoice with get_invoice

2. **Check Status**:
   - Is it in the correct state? (draft/sent/paid/void)
   - Has it been sent? Check sent_at timestamp
   - Any pending actions?

3. **Verify Data**:
   - Customer email correct?
   - Line items and amounts correct?
   - Tax calculations accurate?
   - Payment terms set properly?

4. **Review Events**: Use list_events to see invoice history:
   - When was it created?
   - Email delivery status
   - Any errors logged?

5. **Check Related Objects**:
   - Associated payments
   - Credit notes applied
   - Payment plan status

6. **Diagnose & Recommend**: Based on findings, explain the issue and how to resolve it.`
        }]
      }]
    })
  );

  // Subscription management
  server.registerPrompt(
    'manage-subscription',
    {
      title: 'Manage Subscription',
      description: 'Help with subscription management tasks',
      argsSchema: {
        subscription_id: z.string().describe('The subscription ID to manage'),
        action: z.string().optional().describe('Action: upgrade, downgrade, cancel, pause, or review')
      }
    },
    async ({ subscription_id, action = 'review' }) => {
      let actionSteps = '';
      if (action === 'upgrade' || action === 'downgrade') {
        actionSteps = `
3. **Plan Change**:
   - List available plans with list_plans
   - Use preview_subscription to see prorated charges
   - Confirm timing (immediate vs end of period)
   - Execute change with update_subscription`;
      } else if (action === 'cancel') {
        actionSteps = `
3. **Cancellation**:
   - Check for outstanding balance
   - Determine if immediate or end-of-period
   - Use cancel_subscription with appropriate settings
   - Confirm final invoice handling`;
      } else {
        actionSteps = `
3. **Health Check**:
   - Is customer current on payments?
   - Any failed charges recently?
   - Usage vs plan limits
   - Upsell opportunities?`;
      }

      return {
        messages: [{
          role: 'user',
          content: [{
            type: 'text',
            text: `Help me ${action} subscription ${subscription_id}:

1. **Get Current State**: Use get_subscription to retrieve:
   - Current plan and pricing
   - Billing cycle and next invoice date
   - Status (active, canceled, past_due)
   - Any pending changes

2. **Review History**:
   - List related invoices
   - Check payment history
   - Note any previous changes
${actionSteps}

4. **Summary**: Provide status and recommended next steps.`
          }]
        }]
      };
    }
  );
}
