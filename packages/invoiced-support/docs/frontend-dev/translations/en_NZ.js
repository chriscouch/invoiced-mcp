(function () {
    'use strict';

    if (typeof this.InvoicedConfig.translations === 'undefined') {
        this.InvoicedConfig.translations = {};
    }

    this.InvoicedConfig.translations.en_NZ = {
        address: {
            state: 'State',
            postal_code: 'Postcode',
        },
    };
}).call(this);
