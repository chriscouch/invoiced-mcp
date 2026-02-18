(function () {
    'use strict';

    if (typeof this.InvoicedConfig.translations === 'undefined') {
        this.InvoicedConfig.translations = {};
    }

    this.InvoicedConfig.translations.en_GB = {
        address: {
            city: 'Town or City',
            state: 'County (optional)',
            postal_code: 'Post Code',
        },
    };
}).call(this);
