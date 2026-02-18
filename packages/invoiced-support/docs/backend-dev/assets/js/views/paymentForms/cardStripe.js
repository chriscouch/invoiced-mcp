/* globals InvoicedBillingPortal, Stripe */
(function () {
    'use strict';

    const paymentMethod = {
        init: init,
        capture: capture,
    };
    let stripe, elements, defaultValues;

    InvoicedBillingPortal.payments.register('credit_card', paymentMethod);

    function init() {
        InvoicedBillingPortal.stripe.loadStripeJsV3(() => {
            stripe = Stripe(InvoicedBillingPortal.util.getJsonValue('stripe-card-publishable-key'));
            elements = stripe.elements({
                clientSecret: InvoicedBillingPortal.util.getJsonValue('stripe-card-client-secret'),
                appearance: {
                    theme: 'stripe',
                },
            });

            defaultValues = InvoicedBillingPortal.util.getJsonValue('stripe-card-default-values') || {};

            const paymentElement = elements.create('payment', {
                defaultValues: defaultValues,
            });
            paymentElement.mount('#stripe-card-payment-element');
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
            .confirmPayment({
                elements,
                redirect: 'if_required',
                confirmParams: confirmParams,
            })
            .then(function (result) {
                if (result.error) {
                    InvoicedBillingPortal.util.showError(result.error.message, 'stripe-card-errors');
                    onFailure();
                } else {
                    onSuccess({
                        supportDefault: true,
                        payment_intent: result.paymentIntent.id,
                        gateway_token: result.paymentIntent.payment_method
                    });
                }
            }, onFailure);
    }
})();
