(function () {
    'use strict';

    if (typeof this.InvoicedConfig.translations === 'undefined') {
        this.InvoicedConfig.translations = {};
    }

    this.InvoicedConfig.translations.en_AU = {
        address: {
            state: 'State/Territory',
            postal_code: 'Postcode',
        },
    };
}).call(this);
