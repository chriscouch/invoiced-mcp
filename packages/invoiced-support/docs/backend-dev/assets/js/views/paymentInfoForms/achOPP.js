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

    async function capture(formParameters, onSuccess, onFailure) {
        // tokenize bank account
        if (!accountsMatch('account')) {
            onFailure();
            return;
        }
        const accountFirstName = $('.ach-first_name').val();
        const accountLastName = $('.ach-last_name').val();
        const type = $('[name=ach_account_type]:checked').val();


        let address, city, state, zip, country;

        // add billing address (if available)
        if (typeof formParameters.address1 !== 'undefined' && formParameters.address1) {
            address = formParameters.address1;
            city = formParameters.city;
            state = formParameters.state;
            zip = formParameters.postal_code;
            country = formParameters.country;
        } else if ($('.address.billing').length > 0) {
            address = $('.ach-address1').val();
            city = $('.ach-city').val();
            state = $('.ach-state').val();
            zip = $('.ach-postal-code').val();
            country = $('.ach-country').val();

            // validate the billing address
            if (!address) {
                InvoicedBillingPortal.util.showError('Please fill in your billing address.', 'invoiced-ach-errors');
                onFailure();
                return;
            }
        }

        let customerToken;

        try {
            customerToken = await $.ajax({
                method: 'POST',
                url: '/api/opp/customer',
                headers: {
                    Accept: 'application/json',
                },
                data: {
                    "type": "ach",
                    "firstName": accountFirstName,
                    "lastName": accountLastName,
                }
            });
        } catch (error) {
            InvoicedBillingPortal.util.showError(error.responseJSON.message, 'invoiced-card-errors');
            onFailure();

            return;
        }

        let response;
        const accountNumber = $('#account_number').val();
        try {
            response = await $.ajax({
                method: 'POST',
                url: customerToken.url + '/webservice/addPaymentMethod',
                headers: {
                    Accept: 'application/json',
                },
                dataType: 'json',
                contentType: "application/json; charset=utf-8",
                data: JSON.stringify({
                    customer: {
                        token: customerToken.token
                    },
                    paymentMethod: {
                        accountFirstName: accountFirstName,
                        accountLastName: accountLastName,
                        accountNumber: accountNumber,
                        routingNumber: $('#routing_number').val(),
                        type: type,
                        billingAddress: {
                            street1: address,
                            city: city,
                            state: state,
                            zip: zip,
                            country: country
                        },
                    },
                    authenticationRequest: customerToken.authenticationRequest,
                }),
            });
        } catch (e) {
        }

        if (!response || !response.operationResultObject) {
            onFailure();
        }

        const paymentSource = {
            last_four: accountNumber.slice(-4),
            short_payment_method_token: response.operationResultObject.token.value,
            customer_token: customerToken.token,
            method: 'ach',
            account_holder_type: $('[name=ach_account_holder_type]').val(),
        };
        onSuccess(paymentSource);
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
