(function () {
    'use strict';

    this.InvoicedConfig.emailTemplateOptionsByTemplate = {
        new_invoice_email: [
            {
                id: 'button_text',
                name: 'View Invoice button text',
                text: true,
                default: 'View Invoice',
            },
            {
                id: 'attach_pdf',
                name: 'Attach invoice PDF',
                boolean: true,
                default: true,
            },
            {
                id: 'attach_secondary_files',
                name: 'Attach secondary files',
                boolean: true,
                default: false,
            },
            {
                id: 'send_on_issue',
                name: 'Send automatically when a new invoice is issued',
                boolean: true,
                default: false,
                hidden: true,
            },
            {
                id: 'send_on_autopay_invoice',
                name: 'Send automatically when a new invoice is issued with AutoPay',
                boolean: true,
                default: false,
                hidden: true,
            },
            {
                id: 'send_reminder_every_days',
                name: 'Send a reminder every X days until paid (0 disables reminders)',
                number: true,
                default: 0,
                hidden: true,
            },
            {
                id: 'send_on_subscription_invoice',
                name: 'Send automatically when an invoice is generated from a subscription',
                boolean: true,
                default: true,
                hidden: true,
            },
        ],
        paid_invoice_email: [
            {
                id: 'button_text',
                name: 'View Invoice button text',
                text: true,
                default: '',
            },
            {
                id: 'attach_pdf',
                name: 'Attach invoice PDF',
                boolean: true,
                default: true,
            },
            {
                id: 'attach_secondary_files',
                name: 'Attach secondary files',
                boolean: true,
                default: false,
            },
            {
                id: 'send_on_paid',
                name: 'Send automatically when an invoice is paid',
                boolean: true,
                default: false,
                hidden: true,
            },
        ],
        payment_plan_onboard_email: [
            {
                id: 'button_text',
                name: 'Setup Payment Plan button text',
                text: true,
                default: 'Setup Payment Plan',
            },
            {
                id: 'send_on_issue',
                name: 'Send automatically when a new payment plan is added',
                boolean: true,
                default: false,
                hidden: true,
            },
            {
                id: 'send_reminder_every_days',
                name: 'Send a reminder every X days until customer onboards (0 disables reminders)',
                number: true,
                default: 0,
                hidden: true,
            },
        ],
        payment_receipt_email: [
            {
                id: 'attach_pdf',
                name: 'Attach receipt PDF',
                boolean: true,
                default: true,
            },
            {
                id: 'send_on_charge',
                name: 'Send automatically when a customer is charged',
                boolean: true,
                default: true,
                hidden: true,
            },
        ],
        refund_email: [
            {
                id: 'attach_pdf',
                name: 'Attach receipt PDF',
                boolean: true,
                default: true,
            },
            {
                id: 'send_on_charge',
                name: 'Send automatically when a charge is refunded',
                boolean: true,
                default: true,
                hidden: true,
            },
        ],
        auto_payment_failed_email: [
            {
                id: 'button_text',
                name: 'Update Payment Info button text',
                text: true,
                default: 'Update Payment Info',
            },
            {
                id: 'send_on_charge',
                name: 'Send automatically when auto payment fails',
                boolean: true,
                default: true,
                hidden: true,
            },
        ],
        estimate_email: [
            {
                id: 'button_text',
                name: 'View Estimate button text',
                text: true,
                default: 'View Estimate',
            },
            {
                id: 'attach_pdf',
                name: 'Attach estimate PDF',
                boolean: true,
                default: false,
            },
            {
                id: 'attach_secondary_files',
                name: 'Attach secondary files',
                boolean: true,
                default: false,
            },
            {
                id: 'send_on_issue',
                name: 'Send automatically when a new estimate is added',
                boolean: true,
                default: false,
                hidden: true,
            },
            {
                id: 'send_reminder_every_days',
                name: 'Send a reminder every X days until estimate is approved, declined, or expired',
                number: true,
                default: 0,
                hidden: true,
            },
        ],
        credit_note_email: [
            {
                id: 'button_text',
                name: 'View Credit Note button text',
                text: true,
                default: 'View Credit Note',
            },
            {
                id: 'attach_pdf',
                name: 'Attach credit note PDF',
                boolean: true,
                default: true,
            },
            {
                id: 'attach_secondary_files',
                name: 'Attach secondary files',
                boolean: true,
                default: false,
            },
            {
                id: 'send_on_issue',
                name: 'Send automatically when a new credit note is issued',
                boolean: true,
                default: false,
                hidden: true,
            },
        ],
        subscription_renews_soon_email: [
            {
                id: 'days_before_renewal',
                name: 'Days to send in advance of renewal (0 disables this message)',
                number: true,
                default: 0,
                hidden: true,
            },
            {
                id: 'button_text',
                name: 'Manage Subscription button text',
                text: true,
                default: 'Manage Subscription',
            },
        ],
        subscription_canceled_email: [
            {
                id: 'send_on_cancellation',
                name: 'Send automatically when a subscription is canceled',
                boolean: true,
                default: false,
                hidden: true,
            },
        ],
        subscription_confirmation_email: [
            {
                id: 'send_on_subscribe',
                name: 'Send automatically when a subscription is created',
                boolean: true,
                default: false,
                hidden: true,
            },
            {
                id: 'button_text',
                name: 'Manage Subscription button text',
                text: true,
                default: 'Manage Subscription',
            },
        ],
    };

    this.InvoicedConfig.emailTemplateOptionsByType = {
        invoice: [
            {
                id: 'button_text',
                name: 'View Invoice button text',
                text: true,
                default: 'View Invoice',
            },
            {
                id: 'attach_pdf',
                name: 'Attach invoice PDF',
                boolean: true,
                default: true,
            },
            {
                id: 'attach_secondary_files',
                name: 'Attach secondary files',
                boolean: true,
                default: false,
            },
        ],
        statement: [
            {
                id: 'button_text',
                name: 'View Statement button text',
                text: true,
                default: 'View Statement',
            },
        ],
        payment_plan: [
            {
                id: 'button_text',
                name: 'Setup Payment Plan button text',
                text: true,
                default: 'Setup Payment Plan',
            },
        ],
        estimate: [
            {
                id: 'button_text',
                name: 'View Estimate button text',
                text: true,
                default: 'View Estimate',
            },
            {
                id: 'attach_pdf',
                name: 'Attach estimate PDF',
                boolean: true,
                default: false,
            },
            {
                id: 'attach_secondary_files',
                name: 'Attach secondary files',
                boolean: true,
                default: false,
            },
        ],
        transaction: [
            {
                id: 'attach_pdf',
                name: 'Attach receipt PDF',
                boolean: true,
                default: true,
            },
        ],
        credit_note: [
            {
                id: 'button_text',
                name: 'View Credit Note button text',
                text: true,
                default: 'View Credit Note',
            },
            {
                id: 'attach_pdf',
                name: 'Attach credit note PDF',
                boolean: true,
                default: true,
            },
            {
                id: 'attach_secondary_files',
                name: 'Attach secondary files',
                boolean: true,
                default: false,
            },
        ],
        chasing: [
            {
                id: 'button_text',
                name: 'Pay Now button text',
                text: true,
                default: 'Pay Now',
            },
            {
                id: 'attach_pdf',
                name: 'Attach invoices as PDF',
                boolean: true,
                default: false,
            },
        ],
        subscription: [
            {
                id: 'button_text',
                name: 'Manage Subscription button text',
                text: true,
                default: 'Manage Subscription',
            },
        ],
    };
}).call(this);
