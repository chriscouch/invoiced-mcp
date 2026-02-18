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

    async function capture(formParameters, onSuccess, onFailure) {
        // tokenize CC
        const number = $('.cc-num').val().replace(/ /g, '');
        const cvc = $('.cc-cvc').val();
        const expiry = ($('.cc-exp').val() || '').split('/');

        const accountFirstName = $('.cc-first_name').val();
        const accountLastName = $('.cc-last_name').val();
        let type = $('.input-card-number .type').attr('class').replace('type', '').replace(' ', '');
        switch (true) {
            case type.indexOf('mastercard') === 0:
                type = 'MC';
                break;
            case type.indexOf('discover') === 0:
                type = 'DISC';
                break;
            case type.indexOf('unionpay') === 0:
                type = 'UNION_PAY';
                break;
            default:
                type = type.toUpperCase();
        }

        let address, city, state, zip, country;

        // add billing address (if available)
        if (typeof formParameters.address1 !== 'undefined' && formParameters.address1) {
            address = formParameters.address1;
            city = formParameters.city;
            state = formParameters.state;
            zip = formParameters.postal_code;
            country = formParameters.country;
        } else if ($('.address.billing').length > 0) {
            address = $('.cc-address1').val();
            city = $('.cc-city').val();
            state = $('.cc-state').val();
            zip = $('.cc-postal-code').val();
            country = $('.cc-country').val();

            // validate the billing address
            if (!address) {
                InvoicedBillingPortal.util.showError('Please fill in your billing address.', 'invoiced-card-errors');
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
                    "type": "credit_card",
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
                        CVV: cvc,
                        accountNumber: number,
                        type: type,
                        expireMonth: expiry[0],
                        expireYear: expiry[1],
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
            last_four: number.slice(-4),
            short_payment_method_token: response.operationResultObject.token.value,
            customer_token: customerToken.token,
            method: 'credit_card',
        };
        onSuccess(paymentSource);
    }
})();
