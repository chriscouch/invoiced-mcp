(function () {
    'use strict';

    if (typeof this.InvoicedConfig.translations === 'undefined') {
        this.InvoicedConfig.translations = {};
    }

    this.InvoicedConfig.translations.en_US = {
        address: {
            state: 'State',
            postal_code: 'Zip Code',
        },
        payment_method: {
            check: 'Check',
        },
        payments: {
            labels: {
                check_no: 'Check #',
            },
        },
    };
}).call(this);
