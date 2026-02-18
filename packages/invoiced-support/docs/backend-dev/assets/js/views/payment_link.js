/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    InvoicedBillingPortal.payments.setSubmitHandler(submit);
    InvoicedBillingPortal.bootstrap(run);

    function run() {
        const paymentMethodSelector = $('#paymentMethodSelector');
        selectPaymentMethod(parsePaymentMethod());

        InvoicedBillingPortal.util.initAddressForm('billing');
        InvoicedBillingPortal.util.initAddressForm('shipping');

        paymentMethodSelector.change(function () {
            selectPaymentMethod(parsePaymentMethod());
        });

        $('#paymentLinkSubmitForm').submit(function (e) {
            e.preventDefault();

            // capture the payment information
            InvoicedBillingPortal.util.hideErrors();
            InvoicedBillingPortal.util.showLoading($('#paymentProcessingMessage').text());

            const paymentMethod = parsePaymentMethod();
            if (typeof paymentMethod.settings.paymentSourceId !== 'undefined') {
                const paymentSource = {
                    payment_source_type: paymentMethod.settings.paymentSourceType,
                    payment_source_id: paymentMethod.settings.paymentSourceId,
                };
                InvoicedBillingPortal.payments.submit(paymentSource);
            } else {
                const paymentParameters = {
                    amount: InvoicedBillingPortal.util.getPaymentAmount(),
                    email: $('.customer-email').val(),
                    firstName: $('.customer-first-name').val(),
                    lastName: $('.customer-last-name').val(),
                    company: $('.customer-company').val(),
                    phone: $('.customer-phone').val(),
                    address1: $('.billing-address1').val(),
                    address2: $('.billing-address2').val(),
                    city: $('.billing-city').val(),
                    state: $('.billing-state').val(),
                    postal_code: $('.billing-postal-code').val(),
                    country: $('.billing-country').val(),
                    formData: serializeForm({}),
                };

                InvoicedBillingPortal.payments.capture(
                    paymentMethod.id,
                    paymentParameters,
                    InvoicedBillingPortal.payments.submit,
                    paymentMethodFailed
                );
            }

            return false;
        });

        function parsePaymentMethod() {
            const methodId = paymentMethodSelector.val();
            if (!methodId) {
                return {};
            }

            const selectedOption = paymentMethodSelector.find('option:selected');
            const settings = selectedOption.data('settings');

            return {
                id: methodId,
                settings: settings,
            };
        }

        function selectPaymentMethod(paymentMethod) {
            InvoicedBillingPortal.payments.hideOtherForms(paymentMethod.id);

            if (paymentMethod.settings.convenienceFeePercent) {
                $('#convenience-fee-warning').removeClass('hidden');
                $('#convenience-fee-percent').text(paymentMethod.settings.convenienceFeePercent);
                $('#convenience-fee-amount').text(paymentMethod.settings.convenienceFeeAmount);
                $('#convenience-fee-payment-total')
                    .removeClass('hidden')
                    .text(paymentMethod.settings.convenienceFeeTotal);
                $('#normal-payment-total').addClass('hidden');
            } else {
                $('#convenience-fee-warning').addClass('hidden');
                $('#convenience-fee-payment-total').addClass('hidden');
                $('#normal-payment-total').removeClass('hidden');
            }
        }

        function paymentMethodFailed(wasCanceled) {
            InvoicedBillingPortal.util.hideLoading();
            if (!wasCanceled) {
                InvoicedBillingPortal.util.showError(
                    'Could not validate your payment information. Please check below to make sure you have entered in your payment information correctly.',
                    'payment-link-errors',
                    true
                );
            }
        }
    }

    function submit(paymentSource, success, fail) {
        const submitForm = $('#paymentLinkSubmitForm');
        const url = submitForm.attr('action');

        $.ajax({
            type: 'POST',
            url: url,
            data: serializeForm(paymentSource),
            headers: {
                Accept: 'application/json',
            },
        })
            .done(function (data2) {
                success();

                // take the user to the thank you page
                window.location.href = data2.url;
            })
            .fail(function (data2) {
                fail();

                // show the error message
                let message;
                try {
                    message = JSON.parse(data2.responseText).error;
                } catch (err) {
                    message = 'An unknown error has occurred';
                }
                InvoicedBillingPortal.util.showError(message, 'payment-link-errors');
            });
    }

    function serializeForm(paymentSource) {
        // encode payment source into form
        const html = InvoicedBillingPortal.util.encodeToForm(paymentSource, 'payment_source');
        $('#paymentSourceData').html(html);

        const submitForm = $('#paymentLinkSubmitForm');

        return submitForm.serialize();
    }
})();
