/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    var paymentMethod = {
        init: init,
        capture: capture,
    };

    InvoicedBillingPortal.payments.register('direct_debit', paymentMethod);

    function init() {
        // nothing to do here
    }

    function capture() {
        window.location = $('#gocardlessSetupUrl').val();
    }
})();
