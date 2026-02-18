(function () {
    'use strict';

    if (typeof this.InvoicedConfig.translations === 'undefined') {
        this.InvoicedConfig.translations = {};
    }

    this.InvoicedConfig.translations.en_CA = {
        address: {
            state: 'Province',
            postal_code: 'Postal Code',
        },
    };
}).call(this);
