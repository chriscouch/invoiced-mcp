/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    const paymentMethod = {
        init: init,
        capture: capture,
    };

    InvoicedBillingPortal.payments.register('credit_card', paymentMethod);

    function init() {
        InvoicedBillingPortal.util.initCardForm();
        InvoicedBillingPortal.util.initAddressForm('billing');
    }

    function capture(formParameters, onSuccess, onFailure) {
        // tokenize CC
        const number = $('.cc-num').val();
        const cvc = $('.cc-cvc').val();
        const expiry = ($('.cc-exp').val() || '').split('/');

        let prefillName = null;
        if (typeof formParameters.firstName !== 'undefined' && formParameters.firstName) {
            prefillName = (formParameters.firstName + ' ' + formParameters.lastName).trim();
        } else if (typeof formParameters.company !== 'undefined' && formParameters.company) {
            prefillName = formParameters.company;
        }
        const name = prefillName || $('.cc-name').val();

        // add billing address (if available)
        let address = false;
        if (typeof formParameters.address1 !== 'undefined' && formParameters.address1) {
            address = {
                address1: formParameters.address1,
                address2: formParameters.address2,
                city: formParameters.city,
                state: formParameters.state,
                postal_code: formParameters.postal_code,
                country: formParameters.country,
            };
        } else if ($('.address.billing').length > 0) {
            address = {
                address1: $('.cc-address1').val(),
                address2: null,
                city: $('.cc-city').val(),
                state: $('.cc-state').val(),
                postal_code: $('.cc-postal-code').val(),
                country: $('.cc-country').val(),
            };

            // validate the billing address
            if (!address.address1) {
                InvoicedBillingPortal.util.showError('Please fill in your billing address.', 'invoiced-card-errors');
                onFailure();
                return;
            }
        }

        InvoicedBillingPortal.payments.tokenizeCard(
            number,
            cvc,
            expiry[0],
            expiry[1],
            name,
            address,
            function (status, response) {
                if (status >= 400) {
                    InvoicedBillingPortal.util.showError(response.message, 'invoiced-card-errors');
                    onFailure();
                } else {
                    const paymentSource = {
                        invoiced_token: response.id,
                    };
                    onSuccess(paymentSource);
                }
            }
        );
    }
})();
