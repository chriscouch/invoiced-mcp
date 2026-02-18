(function () {
    'use strict';

    if (typeof this.InvoicedConfig.translations === 'undefined') {
        this.InvoicedConfig.translations = {};
    }

    this.InvoicedConfig.translations.en_IE = {
        address: {
            city: 'Town or City',
            state: 'County (optional)',
            postal_code: 'Eircode (optional)',
        },
    };
}).call(this);
