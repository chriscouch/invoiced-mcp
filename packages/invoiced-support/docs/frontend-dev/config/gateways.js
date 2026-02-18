(function () {
    'use strict';

    this.InvoicedConfig.paymentGatewaysByMethod = {
        ach: [
            'authorizenet',
            'cardknox',
            'cybersource',
            'flywire_payments',
            'intuit',
            'lawpay',
            'nacha',
            'nmi',
            'orbital',
            'payflowpro',
            'stripe',
            'vantiv',
        ],
        credit_card: [
            'authorizenet',
            'braintree',
            'cardknox',
            'cybersource',
            'flywire',
            'flywire_payments',
            'intuit',
            'lawpay',
            'moneris',
            'nmi',
            'orbital',
            'payflowpro',
            'stripe',
            'vantiv',
        ],
        direct_debit: ['gocardless', 'flywire'],
        bank_transfer: ['flywire'],
        online: ['flywire'],
    };
}).call(this);
