/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    const paymentMethod = {
        init: init,
        capture: capture,
    };

    InvoicedBillingPortal.payments.register('ach', paymentMethod);

    function init() {
        InvoicedBillingPortal.util.initAddressForm('ach');
    }

    function capture(formParameters, onSuccess, onFailure) {
        // tokenize bank account
        if (!accountsMatch('account')) {
            onFailure();
            return;
        }

        // add billing address (if available)
        let address = {
            address1: null,
            address2: null,
            city: null,
            state: null,
            postal_code: null,
            country: null,
        };
        if (typeof formParameters.address1 !== 'undefined' && formParameters.address1) {
            address = {
                address1: formParameters.address1,
                address2: formParameters.address2,
                city: formParameters.city,
                state: formParameters.state,
                postal_code: formParameters.postal_code,
                country: formParameters.country,
            };
        } else if ($('.address.ach').length > 0) {
            address = {
                address1: $('.ach-address1').val(),
                address2: null,
                city: $('.ach-city').val(),
                state: $('.ach-state').val(),
                postal_code: $('.ach-postal-code').val(),
                country: $('.ach-country').val(),
            };

            // validate the billing address
            if (!address.address1) {
                InvoicedBillingPortal.util.showError('Please fill in your billing address.', 'invoiced-ach-errors');
                onFailure();
                return;
            }
        }

        let accountHolderName = $('#account_holder_name').val();
        // validate the billing address
        if (!accountHolderName) {
            InvoicedBillingPortal.util.showError('Please fill in the account holder name.', 'invoiced-ach-errors');
            onFailure();
            return;
        }

        onSuccess({
            payment_method: 'ach',
            account_holder_name: accountHolderName,
            account_holder_type: $('input.account_holder_type:checked').val(),
            account_number: $('#account_number').val(),
            routing_number: $('#routing_number').val(),
            address_address1: address.address1,
            address_address2: address.address2,
            address_city: address.city,
            address_state: address.state,
            address_postal_code: address.postal_code,
            address_country: address.country,
        });
    }

    function accountsMatch(field) {
        const a = $('#' + field + '_number').val();
        const b = $('#' + field + '_number_confirm').val();
        if (a !== b) {
            InvoicedBillingPortal.util.showError(
                'The given ' + field + ' numbers do not match. Please verify that your input is correct.',
                'invoiced-ach-errors'
            );
            return false;
        }

        return true;
    }
})();
