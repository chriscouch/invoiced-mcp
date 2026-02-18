/**
 * Invoiced API Client
 * A lightweight client for the Invoiced REST API
 */

const PRODUCTION_URL = 'https://api.invoiced.com';
const SANDBOX_URL = 'https://api.sandbox.invoiced.com';

export class InvoicedClient {
  constructor(apiKey, sandbox = false) {
    this.apiKey = apiKey;
    this.baseUrl = sandbox ? SANDBOX_URL : PRODUCTION_URL;
  }

  /**
   * Make an authenticated request to the Invoiced API
   */
  async request(method, path, data = null, query = null) {
    let url = `${this.baseUrl}${path}`;

    if (query) {
      const params = new URLSearchParams();
      for (const [key, value] of Object.entries(query)) {
        if (value !== undefined && value !== null) {
          if (typeof value === 'object') {
            for (const [subKey, subValue] of Object.entries(value)) {
              params.append(`${key}[${subKey}]`, subValue);
            }
          } else {
            params.append(key, value);
          }
        }
      }
      const queryString = params.toString();
      if (queryString) {
        url += `?${queryString}`;
      }
    }

    const headers = {
      'Authorization': 'Basic ' + Buffer.from(this.apiKey + ':').toString('base64'),
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    };

    const options = {
      method,
      headers
    };

    if (data && (method === 'POST' || method === 'PATCH' || method === 'PUT')) {
      options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);

    // Handle rate limiting
    if (response.status === 429) {
      const retryAfter = response.headers.get('Retry-After') || 60;
      throw new Error(`Rate limited. Retry after ${retryAfter} seconds.`);
    }

    // Handle no content responses
    if (response.status === 204) {
      return { success: true };
    }

    const responseData = await response.json();

    if (!response.ok) {
      const error = responseData.message || `HTTP ${response.status} error`;
      throw new Error(error);
    }

    // Extract pagination info from headers
    const totalCount = response.headers.get('X-Total-Count');
    const linkHeader = response.headers.get('Link');

    if (totalCount !== null) {
      return {
        data: responseData,
        totalCount: parseInt(totalCount, 10),
        links: parseLinkHeader(linkHeader)
      };
    }

    return responseData;
  }

  // Customers
  async listCustomers(params = {}) {
    return this.request('GET', '/customers', null, params);
  }

  async createCustomer(data) {
    return this.request('POST', '/customers', data);
  }

  async getCustomer(id) {
    return this.request('GET', `/customers/${id}`);
  }

  async updateCustomer(id, data) {
    return this.request('PATCH', `/customers/${id}`, data);
  }

  async deleteCustomer(id) {
    return this.request('DELETE', `/customers/${id}`);
  }

  async getCustomerBalance(customerId, currency = null) {
    const params = currency ? { currency } : {};
    return this.request('GET', `/customers/${customerId}/balance`, null, params);
  }

  async sendStatement(customerId, data = {}) {
    return this.request('POST', `/customers/${customerId}/emails`, data);
  }

  async sendStatementSMS(customerId, data = {}) {
    return this.request('POST', `/customers/${customerId}/text_messages`, data);
  }

  async sendStatementLetter(customerId, data = {}) {
    return this.request('POST', `/customers/${customerId}/letters`, data);
  }

  async generateStatement(customerId, params = {}) {
    return this.request('GET', `/customers/${customerId}/statement`, null, params);
  }

  async sendCustomerStatement(id, data) {
    return this.request('POST', `/customers/${id}/emails`, data);
  }

  async consolidateInvoices(customerId, data = {}) {
    return this.request('POST', `/customers/${customerId}/consolidate_invoices`, data);
  }

  // Contacts - accepts args object with customer_id and contact_id
  async listContacts(args = {}) {
    const { customer_id, ...params } = args;
    if (customer_id) {
      return this.request('GET', `/customers/${customer_id}/contacts`, null, params);
    }
    return this.request('GET', '/contacts', null, params);
  }

  async createContact(args) {
    // Schema uses 'customer', support both for backwards compatibility
    const { customer, customer_id, ...data } = args;
    const custId = customer || customer_id;
    return this.request('POST', `/customers/${custId}/contacts`, data);
  }

  async getContact(args) {
    const { customer_id, id } = args;
    return this.request('GET', `/customers/${customer_id}/contacts/${id}`);
  }

  async updateContact(args) {
    const { customer_id, id, ...data } = args;
    return this.request('PATCH', `/customers/${customer_id}/contacts/${id}`, data);
  }

  async deleteContact(args) {
    const { customer_id, id } = args;
    return this.request('DELETE', `/customers/${customer_id}/contacts/${id}`);
  }

  // Invoices
  async listInvoices(params = {}) {
    return this.request('GET', '/invoices', null, params);
  }

  async createInvoice(data) {
    return this.request('POST', '/invoices', data);
  }

  async getInvoice(id, params = {}) {
    return this.request('GET', `/invoices/${id}`, null, params);
  }

  async updateInvoice(id, data) {
    return this.request('PATCH', `/invoices/${id}`, data);
  }

  async deleteInvoice(id) {
    return this.request('DELETE', `/invoices/${id}`);
  }

  async sendInvoice(id, data = {}) {
    return this.request('POST', `/invoices/${id}/emails`, data);
  }

  async payInvoice(id, data) {
    return this.request('POST', `/invoices/${id}/pay`, data);
  }

  async voidInvoice(id) {
    return this.request('POST', `/invoices/${id}/void`);
  }

  async listInvoiceAttachments(invoiceId, params = {}) {
    return this.request('GET', `/invoices/${invoiceId}/attachments`, null, params);
  }

  async addInvoiceAttachment(invoiceId, data) {
    return this.request('POST', `/invoices/${invoiceId}/attachments`, data);
  }

  // Payment Plans
  async listPaymentPlans(params = {}) {
    return this.request('GET', '/payment_plans', null, params);
  }

  async getPaymentPlan(id) {
    // Handle both invoice_id and direct id
    if (typeof id === 'object' && id.invoice_id) {
      return this.request('GET', `/invoices/${id.invoice_id}/payment_plan`);
    }
    return this.request('GET', `/payment_plans/${id}`);
  }

  async createPaymentPlan(args) {
    const { invoice_id, ...data } = args;
    if (invoice_id) {
      return this.request('POST', `/invoices/${invoice_id}/payment_plan`, data);
    }
    return this.request('POST', '/payment_plans', args);
  }

  async updatePaymentPlan(id, data) {
    return this.request('PATCH', `/payment_plans/${id}`, data);
  }

  async deletePaymentPlan(id) {
    // Handle both invoice_id and direct id
    if (typeof id === 'object' && id.invoice_id) {
      return this.request('DELETE', `/invoices/${id.invoice_id}/payment_plan`);
    }
    return this.request('DELETE', `/payment_plans/${id}`);
  }

  // Payments
  async listPayments(params = {}) {
    return this.request('GET', '/payments', null, params);
  }

  async createPayment(data) {
    return this.request('POST', '/payments', data);
  }

  async getPayment(id) {
    return this.request('GET', `/payments/${id}`);
  }

  async updatePayment(id, data) {
    return this.request('PATCH', `/payments/${id}`, data);
  }

  async deletePayment(id) {
    return this.request('DELETE', `/payments/${id}`);
  }

  async sendPaymentReceipt(id, data = {}) {
    return this.request('POST', `/payments/${id}/emails`, data);
  }

  // Credit Notes
  async listCreditNotes(params = {}) {
    return this.request('GET', '/credit_notes', null, params);
  }

  async createCreditNote(data) {
    return this.request('POST', '/credit_notes', data);
  }

  async getCreditNote(id, params = {}) {
    return this.request('GET', `/credit_notes/${id}`, null, params);
  }

  async updateCreditNote(id, data) {
    return this.request('PATCH', `/credit_notes/${id}`, data);
  }

  async deleteCreditNote(id) {
    return this.request('DELETE', `/credit_notes/${id}`);
  }

  async sendCreditNote(id, data = {}) {
    return this.request('POST', `/credit_notes/${id}/emails`, data);
  }

  async voidCreditNote(id) {
    return this.request('POST', `/credit_notes/${id}/void`);
  }

  // Estimates
  async listEstimates(params = {}) {
    return this.request('GET', '/estimates', null, params);
  }

  async createEstimate(data) {
    return this.request('POST', '/estimates', data);
  }

  async getEstimate(id, params = {}) {
    return this.request('GET', `/estimates/${id}`, null, params);
  }

  async updateEstimate(id, data) {
    return this.request('PATCH', `/estimates/${id}`, data);
  }

  async deleteEstimate(id) {
    return this.request('DELETE', `/estimates/${id}`);
  }

  async sendEstimate(id, data = {}) {
    return this.request('POST', `/estimates/${id}/emails`, data);
  }

  async voidEstimate(id) {
    return this.request('POST', `/estimates/${id}/void`);
  }

  async convertEstimateToInvoice(id) {
    return this.request('POST', `/estimates/${id}/invoice`);
  }

  // Charges
  async listCharges(params = {}) {
    return this.request('GET', '/charges', null, params);
  }

  async getCharge(id) {
    return this.request('GET', `/charges/${id}`);
  }

  async createCharge(data) {
    return this.request('POST', '/charges', data);
  }

  async refundCharge(chargeId, data) {
    return this.request('POST', `/charges/${chargeId}/refunds`, data);
  }

  // Refunds
  async listRefunds(params = {}) {
    return this.request('GET', '/refunds', null, params);
  }

  async getRefund(id) {
    return this.request('GET', `/refunds/${id}`);
  }

  async createRefund(data) {
    return this.request('POST', '/refunds', data);
  }

  // Payment Sources
  async listPaymentSources(args = {}) {
    const { customer_id, ...params } = args;
    return this.request('GET', `/customers/${customer_id}/payment_sources`, null, params);
  }

  async getPaymentSource(id) {
    return this.request('GET', `/payment_sources/${id}`);
  }

  async createPaymentSource(args) {
    const { customer_id, ...data } = args;
    return this.request('POST', `/customers/${customer_id}/payment_sources`, data);
  }

  async deletePaymentSource(id) {
    return this.request('DELETE', `/payment_sources/${id}`);
  }

  async deleteCard(customerId, cardId) {
    return this.request('DELETE', `/customers/${customerId}/cards/${cardId}`);
  }

  async deleteBankAccount(customerId, bankAccountId) {
    return this.request('DELETE', `/customers/${customerId}/bank_accounts/${bankAccountId}`);
  }

  // Metered Billing (Pending Line Items)
  async listMeteredBillings(args = {}) {
    const { customer_id, ...params } = args;
    if (customer_id) {
      return this.request('GET', `/customers/${customer_id}/line_items`, null, params);
    }
    return this.request('GET', '/line_items', null, params);
  }

  async createMeteredBilling(args) {
    const { customer_id, ...data } = args;
    return this.request('POST', `/customers/${customer_id}/line_items`, data);
  }

  async listPendingLineItems(customerId, params = {}) {
    return this.request('GET', `/customers/${customerId}/line_items`, null, params);
  }

  async createPendingLineItem(customerId, data) {
    return this.request('POST', `/customers/${customerId}/line_items`, data);
  }

  async getPendingLineItem(customerId, lineItemId) {
    return this.request('GET', `/customers/${customerId}/line_items/${lineItemId}`);
  }

  async updatePendingLineItem(customerId, lineItemId, data) {
    return this.request('PATCH', `/customers/${customerId}/line_items/${lineItemId}`, data);
  }

  async deletePendingLineItem(customerId, lineItemId) {
    return this.request('DELETE', `/customers/${customerId}/line_items/${lineItemId}`);
  }

  // Subscriptions
  async listSubscriptions(params = {}) {
    return this.request('GET', '/subscriptions', null, params);
  }

  async createSubscription(data) {
    return this.request('POST', '/subscriptions', data);
  }

  async previewSubscription(data) {
    return this.request('POST', '/subscriptions/preview', data);
  }

  async getSubscription(id) {
    return this.request('GET', `/subscriptions/${id}`);
  }

  async updateSubscription(id, data) {
    return this.request('PATCH', `/subscriptions/${id}`, data);
  }

  async cancelSubscription(id, args = {}) {
    return this.request('DELETE', `/subscriptions/${id}`, args);
  }

  // Plans
  async listPlans(params = {}) {
    return this.request('GET', '/plans', null, params);
  }

  async createPlan(data) {
    return this.request('POST', '/plans', data);
  }

  async getPlan(id) {
    return this.request('GET', `/plans/${id}`);
  }

  async updatePlan(id, data) {
    return this.request('PATCH', `/plans/${id}`, data);
  }

  async deletePlan(id) {
    return this.request('DELETE', `/plans/${id}`);
  }

  // Coupons
  async listCoupons(params = {}) {
    return this.request('GET', '/coupons', null, params);
  }

  async createCoupon(data) {
    return this.request('POST', '/coupons', data);
  }

  async getCoupon(id) {
    return this.request('GET', `/coupons/${id}`);
  }

  async updateCoupon(id, data) {
    return this.request('PATCH', `/coupons/${id}`, data);
  }

  async deleteCoupon(id) {
    return this.request('DELETE', `/coupons/${id}`);
  }

  // Items (Catalog)
  async listItems(params = {}) {
    return this.request('GET', '/items', null, params);
  }

  async createItem(data) {
    return this.request('POST', '/items', data);
  }

  async getItem(id) {
    return this.request('GET', `/items/${id}`);
  }

  async updateItem(id, data) {
    return this.request('PATCH', `/items/${id}`, data);
  }

  async deleteItem(id) {
    return this.request('DELETE', `/items/${id}`);
  }

  // Tax Rates
  async listTaxRates(params = {}) {
    return this.request('GET', '/tax_rates', null, params);
  }

  async createTaxRate(data) {
    return this.request('POST', '/tax_rates', data);
  }

  async getTaxRate(id) {
    return this.request('GET', `/tax_rates/${id}`);
  }

  async updateTaxRate(id, data) {
    return this.request('PATCH', `/tax_rates/${id}`, data);
  }

  async deleteTaxRate(id) {
    return this.request('DELETE', `/tax_rates/${id}`);
  }

  // Credit Balances
  async listCreditBalances(params = {}) {
    return this.request('GET', '/credit_balance_adjustments', null, params);
  }

  async getCreditBalance(id) {
    return this.request('GET', `/credit_balance_adjustments/${id}`);
  }

  async createCreditBalance(data) {
    return this.request('POST', '/credit_balance_adjustments', data);
  }

  // Credit Balance Adjustments (aliases)
  async listCreditBalanceAdjustments(params = {}) {
    return this.request('GET', '/credit_balance_adjustments', null, params);
  }

  async createCreditBalanceAdjustment(data) {
    return this.request('POST', '/credit_balance_adjustments', data);
  }

  async getCreditBalanceAdjustment(id) {
    return this.request('GET', `/credit_balance_adjustments/${id}`);
  }

  // Payment Terms
  async listPaymentTerms(params = {}) {
    return this.request('GET', '/payment_terms', null, params);
  }

  async createPaymentTerms(data) {
    return this.request('POST', '/payment_terms', data);
  }

  async getPaymentTerms(id) {
    return this.request('GET', `/payment_terms/${id}`);
  }

  async updatePaymentTerms(id, data) {
    return this.request('PATCH', `/payment_terms/${id}`, data);
  }

  async deletePaymentTerms(id) {
    return this.request('DELETE', `/payment_terms/${id}`);
  }

  // Late Fee Schedules
  async listLateFeeSchedules(params = {}) {
    return this.request('GET', '/late_fee_schedules', null, params);
  }

  async createLateFeeSchedule(data) {
    return this.request('POST', '/late_fee_schedules', data);
  }

  async getLateFeeSchedule(id) {
    return this.request('GET', `/late_fee_schedules/${id}`);
  }

  async updateLateFeeSchedule(id, data) {
    return this.request('PATCH', `/late_fee_schedules/${id}`, data);
  }

  async deleteLateFeeSchedule(id) {
    return this.request('DELETE', `/late_fee_schedules/${id}`);
  }

  // Custom Fields
  async listCustomFields(params = {}) {
    return this.request('GET', '/custom_fields', null, params);
  }

  async createCustomField(data) {
    return this.request('POST', '/custom_fields', data);
  }

  async getCustomField(id) {
    return this.request('GET', `/custom_fields/${id}`);
  }

  async updateCustomField(id, data) {
    return this.request('PATCH', `/custom_fields/${id}`, data);
  }

  async deleteCustomField(id) {
    return this.request('DELETE', `/custom_fields/${id}`);
  }

  // Email Templates
  async listEmailTemplates(params = {}) {
    return this.request('GET', '/email_templates', null, params);
  }

  async createEmailTemplate(data) {
    return this.request('POST', '/email_templates', data);
  }

  async getEmailTemplate(id) {
    return this.request('GET', `/email_templates/${id}`);
  }

  async updateEmailTemplate(id, data) {
    return this.request('PATCH', `/email_templates/${id}`, data);
  }

  async deleteEmailTemplate(id) {
    return this.request('DELETE', `/email_templates/${id}`);
  }

  // SMS Templates
  async listSmsTemplates(params = {}) {
    return this.request('GET', '/sms_templates', null, params);
  }

  async createSmsTemplate(data) {
    return this.request('POST', '/sms_templates', data);
  }

  async getSmsTemplate(id) {
    return this.request('GET', `/sms_templates/${id}`);
  }

  async updateSmsTemplate(id, data) {
    return this.request('PATCH', `/sms_templates/${id}`, data);
  }

  async deleteSmsTemplate(id) {
    return this.request('DELETE', `/sms_templates/${id}`);
  }

  // Chasing Cadences
  async listChasingCadences(params = {}) {
    return this.request('GET', '/chasing_cadences', null, params);
  }

  async createChasingCadence(data) {
    return this.request('POST', '/chasing_cadences', data);
  }

  async getChasingCadence(id) {
    return this.request('GET', `/chasing_cadences/${id}`);
  }

  async updateChasingCadence(id, data) {
    return this.request('PATCH', `/chasing_cadences/${id}`, data);
  }

  async deleteChasingCadence(id) {
    return this.request('DELETE', `/chasing_cadences/${id}`);
  }

  async runChasingCadence(id) {
    return this.request('POST', `/chasing_cadences/${id}/run`);
  }

  async assignChasingCadence(id, data) {
    return this.request('POST', `/chasing_cadences/${id}/assign`, data);
  }

  // Automation Workflows (correct endpoint is /automation_workflows)
  async listAutomations(params = {}) {
    return this.request('GET', '/automation_workflows', null, params);
  }

  async createAutomation(data) {
    return this.request('POST', '/automation_workflows', data);
  }

  async getAutomation(id) {
    return this.request('GET', `/automation_workflows/${id}`);
  }

  async updateAutomation(id, data) {
    return this.request('PATCH', `/automation_workflows/${id}`, data);
  }

  async deleteAutomation(id) {
    return this.request('DELETE', `/automation_workflows/${id}`);
  }

  async triggerAutomation(data) {
    return this.request('POST', '/automation_workflows/manual_trigger', data);
  }

  async enrollInAutomation(data) {
    return this.request('POST', '/automation_workflows/enrollment', data);
  }

  async unenrollFromAutomation(enrollmentId) {
    return this.request('DELETE', `/automation_workflows/enrollment/${enrollmentId}`);
  }

  async listAutomationRuns(params = {}) {
    return this.request('GET', '/automation_workflow_runs', null, params);
  }

  // Tasks
  async listTasks(params = {}) {
    return this.request('GET', '/tasks', null, params);
  }

  async createTask(data) {
    return this.request('POST', '/tasks', data);
  }

  async getTask(id) {
    return this.request('GET', `/tasks/${id}`);
  }

  async updateTask(id, data) {
    return this.request('PATCH', `/tasks/${id}`, data);
  }

  async deleteTask(id) {
    return this.request('DELETE', `/tasks/${id}`);
  }

  async completeTask(id) {
    return this.request('POST', `/tasks/${id}/complete`);
  }

  async reopenTask(id) {
    return this.request('POST', `/tasks/${id}/reopen`);
  }

  // Events
  async listEvents(params = {}) {
    return this.request('GET', '/events', null, params);
  }

  async getEvent(id) {
    return this.request('GET', `/events/${id}`);
  }

  // Webhooks
  async listWebhooks(params = {}) {
    return this.request('GET', '/webhooks', null, params);
  }

  async createWebhook(data) {
    return this.request('POST', '/webhooks', data);
  }

  async getWebhook(id) {
    return this.request('GET', `/webhooks/${id}`);
  }

  async updateWebhook(id, data) {
    return this.request('PATCH', `/webhooks/${id}`, data);
  }

  async deleteWebhook(id) {
    return this.request('DELETE', `/webhooks/${id}`);
  }

  async listWebhookAttempts(webhookId, params = {}) {
    return this.request('GET', `/webhooks/${webhookId}/attempts`, null, params);
  }

  async retryWebhookAttempt(webhookId, attemptId) {
    return this.request('POST', `/webhooks/${webhookId}/attempts/${attemptId}/retry`);
  }

  async retryWebhook(webhookId, attemptId) {
    return this.request('POST', `/webhooks/${webhookId}/attempts/${attemptId}/retry`);
  }

  // Files
  async listFiles(params = {}) {
    return this.request('GET', '/files', null, params);
  }

  async getFile(id) {
    return this.request('GET', `/files/${id}`);
  }

  async createFile(data) {
    return this.request('POST', '/files', data);
  }

  async deleteFile(id) {
    return this.request('DELETE', `/files/${id}`);
  }

  // Notes
  async listNotes(params = {}) {
    return this.request('GET', '/notes', null, params);
  }

  async createNote(data) {
    return this.request('POST', '/notes', data);
  }

  async getNote(id) {
    return this.request('GET', `/notes/${id}`);
  }

  async updateNote(id, data) {
    return this.request('PATCH', `/notes/${id}`, data);
  }

  async deleteNote(id) {
    return this.request('DELETE', `/notes/${id}`);
  }

  // Payment Links
  async listPaymentLinks(params = {}) {
    return this.request('GET', '/payment_links', null, params);
  }

  async createPaymentLink(data) {
    return this.request('POST', '/payment_links', data);
  }

  async getPaymentLink(id) {
    return this.request('GET', `/payment_links/${id}`);
  }

  async updatePaymentLink(id, data) {
    return this.request('PATCH', `/payment_links/${id}`, data);
  }

  async deletePaymentLink(id) {
    return this.request('DELETE', `/payment_links/${id}`);
  }

  async listPaymentLinkSessions(paymentLinkId, params = {}) {
    return this.request('GET', `/payment_links/${paymentLinkId}/sessions`, null, params);
  }

  // Sign-Up Pages
  async listSignUpPages(params = {}) {
    return this.request('GET', '/sign_up_pages', null, params);
  }

  async createSignUpPage(data) {
    return this.request('POST', '/sign_up_pages', data);
  }

  async getSignUpPage(id) {
    return this.request('GET', `/sign_up_pages/${id}`);
  }

  async updateSignUpPage(id, data) {
    return this.request('PATCH', `/sign_up_pages/${id}`, data);
  }

  async deleteSignUpPage(id) {
    return this.request('DELETE', `/sign_up_pages/${id}`);
  }

  // Inboxes and Email
  async listInboxes(params = {}) {
    return this.request('GET', '/inboxes', null, params);
  }

  async getInbox(id) {
    return this.request('GET', `/inboxes/${id}`);
  }

  async sendEmail(inboxId, data) {
    return this.request('POST', `/inboxes/${inboxId}/emails`, data);
  }

  async listInboxThreads(inboxId, params = {}) {
    return this.request('GET', `/inboxes/${inboxId}/threads`, null, params);
  }

  async getEmailThread(threadId) {
    return this.request('GET', `/threads/${threadId}`);
  }

  async updateEmailThread(threadId, data) {
    return this.request('PATCH', `/threads/${threadId}`, data);
  }

  async getEmailThreadByDocument(documentType, documentId) {
    return this.request('GET', `/threads`, null, { document_type: documentType, document_id: documentId });
  }

  async listInboxEmails(inboxId, params = {}) {
    return this.request('GET', `/inboxes/${inboxId}/emails`, null, params);
  }

  async listThreadEmails(threadId, params = {}) {
    return this.request('GET', `/threads/${threadId}/emails`, null, params);
  }

  async getInboxEmail(emailId) {
    return this.request('GET', `/emails/${emailId}`);
  }

  async getEmailMessage(emailId) {
    return this.request('GET', `/emails/${emailId}`);
  }

  async listThreadNotes(threadId, params = {}) {
    return this.request('GET', `/threads/${threadId}/notes`, null, params);
  }

  async createThreadNote(threadId, data) {
    return this.request('POST', `/threads/${threadId}/notes`, data);
  }

  async listEmailAttachments(emailId, params = {}) {
    return this.request('GET', `/emails/${emailId}/attachments`, null, params);
  }

  async emailAutocomplete(args) {
    // Correct endpoint is /autocomplete/emails with ?term= parameter
    const { query, ...params } = args;
    return this.request('GET', '/autocomplete/emails', null, { term: query, ...params });
  }

  // Reports (correct method is POST /reports to generate)
  async generateReport(args = {}) {
    // Schema uses 'type', support both for backwards compatibility
    const { type, report_type, ...params } = args;
    const reportType = type || report_type;
    return this.request('POST', '/reports', { type: reportType, ...params });
  }

  async getReport(id) {
    return this.request('GET', `/reports/${id}`);
  }

  async downloadReport(id) {
    return this.request('POST', `/reports/${id}/download`);
  }

  async refreshReport(id) {
    return this.request('POST', `/reports/${id}/refresh`);
  }

  async listScheduledReports(params = {}) {
    return this.request('GET', '/scheduled_reports', null, params);
  }

  async createScheduledReport(data) {
    return this.request('POST', '/scheduled_reports', data);
  }

  async getScheduledReport(id) {
    return this.request('GET', `/scheduled_reports/${id}`);
  }

  async updateScheduledReport(id, data) {
    return this.request('PATCH', `/scheduled_reports/${id}`, data);
  }

  async deleteScheduledReport(id) {
    return this.request('DELETE', `/scheduled_reports/${id}`);
  }

  // Exports
  async listExports(params = {}) {
    return this.request('GET', '/exports', null, params);
  }

  async createExport(data) {
    return this.request('POST', '/exports', data);
  }

  async getExport(id) {
    return this.request('GET', `/exports/${id}`);
  }

  async deleteExport(id) {
    return this.request('DELETE', `/exports/${id}`);
  }

  // Company (correct endpoint is /companies/current)
  async getCompany() {
    return this.request('GET', '/companies/current');
  }

  async updateCompany(id, data) {
    return this.request('PATCH', `/companies/${id}`, data);
  }

  // Members
  async listMembers(params = {}) {
    return this.request('GET', '/members', null, params);
  }

  async createMember(data) {
    return this.request('POST', '/members', data);
  }

  async getMember(id) {
    return this.request('GET', `/members/${id}`);
  }

  async updateMember(id, data) {
    return this.request('PATCH', `/members/${id}`, data);
  }

  async deleteMember(id) {
    return this.request('DELETE', `/members/${id}`);
  }

  // Roles
  async listRoles(params = {}) {
    return this.request('GET', '/roles', null, params);
  }

  async createRole(data) {
    return this.request('POST', '/roles', data);
  }

  async getRole(id) {
    return this.request('GET', `/roles/${id}`);
  }

  async updateRole(id, data) {
    return this.request('PATCH', `/roles/${id}`, data);
  }

  async deleteRole(id) {
    return this.request('DELETE', `/roles/${id}`);
  }

  // API Keys
  async listApiKeys(params = {}) {
    return this.request('GET', '/api_keys', null, params);
  }

  async createApiKey(data) {
    return this.request('POST', '/api_keys', data);
  }

  async deleteApiKey(id) {
    return this.request('DELETE', `/api_keys/${id}`);
  }
}

/**
 * Parse RFC 5988 Link header for pagination
 */
function parseLinkHeader(header) {
  if (!header) return {};

  const links = {};
  const parts = header.split(',');

  for (const part of parts) {
    const match = part.match(/<([^>]+)>;\s*rel="([^"]+)"/);
    if (match) {
      links[match[2]] = match[1];
    }
  }

  return links;
}

export default InvoicedClient;
