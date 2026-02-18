/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    InvoicedBillingPortal.bootstrap(run);

    InvoicedBillingPortal.payments.onSelected(function () {
        $('#paymentSourceForm').submit();
    });

    function run() {
        const paymentMethodSelector = $('#paymentMethodSelector');
        paymentMethodSelector.change(function () {
            selectPaymentMethod(parsePaymentMethod());
        });

        $('#paymentSourceForm').submit(onSubmit);

        selectPaymentMethod(parsePaymentMethod());

        function parsePaymentMethod() {
            const methodId = paymentMethodSelector.val();

            return {
                id: methodId,
                // Currently this is not used. If ever needed to be configurable
                // then this should be implemented in the payment info view class.
                isSubmittable: true,
            };
        }

        function selectPaymentMethod(paymentMethod) {
            InvoicedBillingPortal.payments.hideOtherForms(paymentMethod.id);

            if (paymentMethod.isSubmittable) {
                $('.submittable-hidden-field').removeClass('hidden');
            } else {
                $('.submittable-hidden-field').addClass('hidden');
            }
        }

        function onSubmit(e) {
            e.preventDefault();

            InvoicedBillingPortal.util.showLoading($('#paymentProcessingMessage').text());
            InvoicedBillingPortal.util.hideErrors();

            const selectedMethod = parsePaymentMethod();
            // Get if the payment source should be the customer's default.
            // The checkbox missing from the page implies the customer has no pre-existing
            // saved payment methods, so it should be set to the default.
            const $makeDefault = $('#makeDefault');
            const paymentParameters = {
                makeDefault: $makeDefault.length === 0 || $makeDefault.is(':checked'),
                formData: serializeForm({}),
            };
            InvoicedBillingPortal.payments.capture(selectedMethod.id, paymentParameters, save, paymentMethodFailed);

            return false;
        }

        function save(paymentSource) {
            const customerId = $('#paymentSourceCustomer').val();
            const paymentMethod = parsePaymentMethod();
            const url = '/api/paymentInfo/' + customerId + '/' + paymentMethod.id;

            $.ajax({
                method: 'POST',
                url: url,
                data: serializeForm(paymentSource),
                headers: {
                    Accept: 'application/json',
                },
            })
                .then(function (data) {
                    window.location.href = data.url;
                })
                .fail(function (data) {
                    // show the error message
                    let message;
                    try {
                        message = JSON.parse(data.responseText).error;
                    } catch (err) {
                        message = 'An unknown error has occurred';
                    }
                    InvoicedBillingPortal.util.showError(message, 'payment-source-errors');
                });
        }

        function paymentMethodFailed(wasCanceled) {
            InvoicedBillingPortal.util.hideLoading();
            if (!wasCanceled) {
                InvoicedBillingPortal.util.showError(
                    'Could not validate your payment information. Please check below to make sure you have entered in your payment information correctly.',
                    'payment-source-errors',
                    true
                );
            }
        }

        function serializeForm(paymentSource) {
            const html = InvoicedBillingPortal.util.encodeToForm(paymentSource);
            $('#paymentSourceData').html(html);

            // Get if the customer is enrolling in AutoPay.
            const enrollAutoPay = $('#enrollAutoPay').is(':checked') ? 1 : 0;
            $('#paymentEnrollAutoPay').val(enrollAutoPay);

            // Get if the payment source should be the customer's default.
            // The checkbox missing from the page implies the customer has no pre-existing
            // saved payment methods, so it should be set to the default.
            const $makeDefault = $('#makeDefault');
            let makeDefault = $makeDefault.length === 0 || $makeDefault.is(':checked');
            $('#paymentMakeDefault').val(makeDefault ? 1 : 0);

            return $('#paymentSourceSubmitForm').serialize();
        }
    }
})();
