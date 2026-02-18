/* globals InvoicedBillingPortal, Stripe */
(function () {
    'use strict';

    const paymentMethod = {
        init: init,
        capture: capture,
    };
    let stripe, elements, defaultValues;

    InvoicedBillingPortal.payments.register('ach', paymentMethod);

    function init() {
        InvoicedBillingPortal.stripe.loadStripeJsV3(() => {
            stripe = Stripe(InvoicedBillingPortal.util.getJsonValue('stripe-ach-publishable-key'));
            elements = stripe.elements({
                clientSecret: InvoicedBillingPortal.util.getJsonValue('stripe-ach-client-secret'),
                appearance: {
                    theme: 'stripe',
                },
            });

            defaultValues = InvoicedBillingPortal.util.getJsonValue('stripe-ach-default-values') || {};

            const paymentElement = elements.create('payment', {
                defaultValues: defaultValues,
                fields: {
                    billingDetails: {
                        name: 'auto',
                        email: neverAskForEmail(defaultValues) ? 'never' : 'auto',
                        address: neverAskForAddress(defaultValues) ? 'never' : 'auto',
                        phone: neverAskForPhone(defaultValues) ? 'never' : 'auto',
                    },
                },
            });
            paymentElement.mount('#stripe-ach-payment-element');
        });
    }

    function capture(parameters, onSuccess, onFailure) {
        const confirmParams = {};
        if (defaultValues && defaultValues.billingDetails) {
            confirmParams.payment_method_data = {
                billing_details: defaultValues.billingDetails,
            };
        }

        stripe
            .confirmSetup({
                elements,
                redirect: 'if_required',
                confirmParams: confirmParams,
            })
            .then(function (result) {
                if (result.error) {
                    InvoicedBillingPortal.util.showError(result.error.message, 'stripe-ach-errors');
                    onFailure();
                } else {
                    onSuccess({
                        gateway_token: result.setupIntent.id,
                    });
                }
            }, onFailure);
    }

    function neverAskForEmail(values) {
        return values && values.billingDetails && values.billingDetails.email;
    }

    function neverAskForAddress(values) {
        return (
            values &&
            values.billingDetails &&
            values.billingDetails.address.line1 &&
            values.billingDetails.address.country
        );
    }

    function neverAskForPhone(values) {
        return values && values.billingDetails && values.billingDetails.phone;
    }
})();
