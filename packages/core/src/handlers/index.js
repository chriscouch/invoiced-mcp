import { invoiceHandlers } from './invoice-handlers.js';
import { customerHandlers } from './customer-handlers.js';
import { paymentHandlers } from './payment-handlers.js';
import { estimateHandlers } from './estimate-handlers.js';
import { subscriptionHandlers } from './subscription-handlers.js';
import { planHandlers } from './plan-handlers.js';
import { chasingCadenceHandlers } from './chasing-cadence-handlers.js';
import { itemHandlers } from './item-handlers.js';
import { taxRateHandlers } from './tax-rate-handlers.js';
import { paymentLinkHandlers } from './payment-link-handlers.js';
import { creditNoteHandlers } from './credit-note-handlers.js';
import { contactHandlers } from './contact-handlers.js';
import { emailHandlers } from './email-handlers.js';
import { emailTemplateHandlers } from './email-template-handlers.js';
import { taskHandlers } from './task-handlers.js';
import { eventHandlers } from './event-handlers.js';
import { noteHandlers } from './note-handlers.js';
import { creditBalanceHandlers } from './credit-balance-handlers.js';
import { meteredBillingHandlers } from './metered-billing-handlers.js';
import { couponHandlers } from './coupon-handlers.js';
import { fileHandlers } from './file-handlers.js';
import { paymentPlanHandlers } from './payment-plan-handlers.js';
import { chargeHandlers } from './charge-handlers.js';
import { refundHandlers } from './refund-handlers.js';
import { paymentSourceHandlers } from './payment-source-handlers.js';
import { webhookHandlers } from './webhook-handlers.js';
import { balanceStatementHandlers } from './balance-statement-handlers.js';
import { companyHandlers } from './company-handlers.js';
import { reportHandlers } from './report-handlers.js';

const allHandlers = {
  ...invoiceHandlers,
  ...customerHandlers,
  ...paymentHandlers,
  ...estimateHandlers,
  ...subscriptionHandlers,
  ...planHandlers,
  ...chasingCadenceHandlers,
  ...itemHandlers,
  ...taxRateHandlers,
  ...paymentLinkHandlers,
  ...creditNoteHandlers,
  ...contactHandlers,
  ...emailHandlers,
  ...emailTemplateHandlers,
  ...taskHandlers,
  ...eventHandlers,
  ...noteHandlers,
  ...creditBalanceHandlers,
  ...meteredBillingHandlers,
  ...couponHandlers,
  ...fileHandlers,
  ...paymentPlanHandlers,
  ...chargeHandlers,
  ...refundHandlers,
  ...paymentSourceHandlers,
  ...webhookHandlers,
  ...balanceStatementHandlers,
  ...companyHandlers,
  ...reportHandlers,
};

export async function handleToolCall(toolName, invoiced, args) {
  const handler = allHandlers[toolName];
  if (!handler) {
    throw new Error(`Unknown tool: ${toolName}`);
  }

  const result = await handler(invoiced, args);
  
  if (result && result.content) {
    return result;
  }

  return {
    content: [
      {
        type: "text",
        text: JSON.stringify(result, null, 2),
      },
    ],
  };
}

export { 
  invoiceHandlers,
  customerHandlers, 
  paymentHandlers,
  estimateHandlers,
  subscriptionHandlers,
  planHandlers,
  chasingCadenceHandlers,
  itemHandlers,
  taxRateHandlers,
  paymentLinkHandlers,
  creditNoteHandlers,
  contactHandlers,
  emailHandlers,
  emailTemplateHandlers,
  taskHandlers,
  eventHandlers,
  noteHandlers,
  creditBalanceHandlers,
  meteredBillingHandlers,
  couponHandlers,
  fileHandlers,
  paymentPlanHandlers,
  chargeHandlers,
  refundHandlers,
  paymentSourceHandlers,
  webhookHandlers,
  balanceStatementHandlers,
  companyHandlers,
  reportHandlers,
  allHandlers
};