/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    InvoicedBillingPortal.payments.setSubmitHandler(submit);
    InvoicedBillingPortal.bootstrap(run);

    function run() {
        const paymentMethodSelector = $('#paymentMethodSelector');
        const forceMakeDefault = $('#paymentMakeDefault').val() === '1';
        const forceEnrollAutoPay = $('#paymentEnrollAutoPay').val() === '1';

        selectPaymentMethod(parsePaymentMethod());

        $('.view-all-display-items').click(function (e) {
            e.preventDefault();

            $('.display-item:not(.convenience-fee)').removeClass('hidden');
            $(this).parents('tr.view-all').remove();

            return false;
        });

        paymentMethodSelector.change(function () {
            selectPaymentMethod(parsePaymentMethod());
        });

        $('#paymentInfoForm').submit(function (e) {
            e.preventDefault();

            // capture the payment information
            InvoicedBillingPortal.util.hideErrors();
            InvoicedBillingPortal.util.showLoading($('#paymentProcessingMessage').text());

            const paymentMethod = parsePaymentMethod();
            if (paymentMethod.isSaved) {
                // a saved method has the ID in this format...
                // saved:card:1234
                const paymentSource = {
                    payment_source_type: paymentMethod.savedType,
                    payment_source_id: paymentMethod.savedId,
                };

                if ($('.saved-card-cvc').length > 0 && paymentSource.payment_source_type === 'card') {
                    paymentSource.cvc = $('.cc-cvc', '.saved-card-cvc').val();
                }

                pay(paymentSource);
            } else {
                const paymentParameters = {
                    makeDefault: forceMakeDefault || $('#makeDefault').is(':checked'),
                    enrollInAutoPay: forceEnrollAutoPay || $('#enrollAutoPay').is(':checked'),
                    formData: serializeForm(paymentMethod.id, {}),
                };

                if (paymentMethod.hasReceiptEmail) {
                    const email = getReceiptEmail(paymentMethod.id);
                    if (email) {
                        paymentParameters.email = email;
                    }
                }

                InvoicedBillingPortal.payments.capture(paymentMethod.id, paymentParameters, pay, paymentMethodFailed);
            }

            return false;
        });

        if (!InvoicedBillingPortal.payments.isRegistered('zero_amount')) {
            InvoicedBillingPortal.payments.register('zero_amount', {
                capture: function (parameters, pay, paymentMethodFailed) {
                    try {
                        pay();
                    } catch (e) {
                        paymentMethodFailed();
                    }
                },
            });
            selectPaymentMethod(parsePaymentMethod());
        }

        function selectPaymentMethod(paymentMethod) {
            InvoicedBillingPortal.payments.hideOtherForms(paymentMethod.id);
            if (paymentMethod.convenienceFee) {
                $('#convenience-fee-warning').removeClass('hidden');
                $('#convenience-fee-payment-total').removeClass('hidden');
                $('#normal-payment-total').addClass('hidden');
            } else {
                $('#convenience-fee-warning').addClass('hidden');
                $('#convenience-fee-payment-total').addClass('hidden');
                $('#normal-payment-total').removeClass('hidden');
            }

            if (paymentMethod.isSubmittable) {
                $('.submittable-hidden-field').removeClass('hidden');
            } else {
                $('.submittable-hidden-field').addClass('hidden');
            }

            if (paymentMethod.hasReceiptEmail) {
                $('.receipt-email-div').removeClass('hidden');
                const email = getReceiptEmail(paymentMethod.id);
                if (email) {
                    $('#receiptEmailForm').val(email);
                }
            } else {
                $('.receipt-email-div').addClass('hidden');
            }

            if (paymentMethod.supportsVaulting) {
                $('.vaulting-hidden-field').removeClass('hidden');
            } else {
                $('.vaulting-hidden-field').addClass('hidden');
            }

            if (paymentMethod.supportsAutoPay) {
                $('.autopay-hidden-field').removeClass('hidden');
            } else {
                $('.autopay-hidden-field').addClass('hidden');
            }

            if (paymentMethod.isSaved && paymentMethod.savedType === 'card') {
                $('.saved-card-cvc').removeClass('hidden');
            } else {
                $('.saved-card-cvc').addClass('hidden');
            }

            if (paymentMethod.id === 'zero_amount') {
                $('.receipt-email-div').addClass('hidden');
                $('.pay-with-div').addClass('hidden');
            } else {
                // showing receipt email handled above
                $('.pay-with-div').removeClass('hidden');
            }
        }

        function paymentMethodFailed(wasCanceled) {
            InvoicedBillingPortal.util.hideLoading();
            if (!wasCanceled) {
                InvoicedBillingPortal.util.showError(
                    'Could not validate your payment information. Please check below to make sure you have entered in your payment information correctly.',
                    'invoice-payment-errors',
                    true
                );
            }
        }

        function pay(paymentSource) {
            // look for a receipt email address
            const email = $('#receiptEmailForm')
                .filter(function () {
                    return this.value.length !== 0;
                })
                .val();

            if (email) {
                $('#receiptEmail').val(email);
            }

            // If the make default option is preselected then we always
            // want to obey it. Otherwise, we will use the user's choice
            // whether to save the card as the default.
            if (paymentSource && (paymentSource.supportDefault === false || paymentSource.forceUnmakeDefault)) {
                $('#paymentMakeDefault').val(0);
            } else if (forceMakeDefault || $('#makeDefault').is(':checked')) {
                $('#paymentMakeDefault').val(1);
            }
            // If the enroll AutoPay option is preselected then we always
            // want to obey it. Otherwise, we will use the user's choice
            // about enrolling in AutoPay.
            if (forceEnrollAutoPay || $('#enrollAutoPay').is(':checked')) {
                $('#paymentEnrollAutoPay').val(1);
            } else {
                $('#paymentEnrollAutoPay').val(0);
            }

            InvoicedBillingPortal.payments.submit(paymentSource);
        }
    }

    function submit(paymentSource, success, fail) {
        // Add selected payment method to the hidden payment form
        const paymentMethod = parsePaymentMethod();
        if (!paymentSource && paymentMethod.id === 'zero_amount') {
            paymentSource = {};
        }

        const submitForm = $('#paymentSubmitForm');
        const url = submitForm.attr('action');

        $.ajax({
            type: 'POST',
            url: url,
            data: serializeForm(paymentMethod.id, paymentSource),
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
                InvoicedBillingPortal.util.showError(message, 'invoice-payment-errors');
            });
    }

    function parsePaymentMethod() {
        let methodId, isSubmittable, hasReceiptEmail, supportsVaulting, supportsAutoPay, supportsConvenienceFee;
        if (parseFloat(InvoicedBillingPortal.util.getPaymentAmount()) === 0) {
            methodId = 'zero_amount';
            isSubmittable = true;
            hasReceiptEmail = false;
            supportsVaulting = false;
            supportsAutoPay = false;
            supportsConvenienceFee = false;
        } else {
            const paymentMethodSelector = $('#paymentMethodSelector');
            methodId = paymentMethodSelector.val();
            const selectedOption = paymentMethodSelector.find('option:selected');
            isSubmittable = selectedOption.data('is-submittable');
            hasReceiptEmail = selectedOption.data('has-receipt-email');
            supportsVaulting = selectedOption.data('supports-vaulting');
            supportsAutoPay = selectedOption.data('supports-autopay');
            supportsConvenienceFee = selectedOption.data('supports-convenience-fee');
        }

        if (!methodId) {
            return {};
        }

        const savedMethod = savedMethodId(methodId);

        return {
            id: methodId,
            isSubmittable: isSubmittable,
            hasReceiptEmail: hasReceiptEmail,
            supportsVaulting: supportsVaulting,
            supportsAutoPay: supportsAutoPay,
            convenienceFee: supportsConvenienceFee,
            isSaved: isSavedMethod(methodId),
            savedType: savedMethod ? savedMethod.type : null,
            savedId: savedMethod ? savedMethod.id : null,
        };
    }

    function isSavedMethod(methodId) {
        return methodId.indexOf('saved:') === 0;
    }

    function savedMethodId(methodId) {
        if (!isSavedMethod(methodId)) {
            return null;
        }

        return {
            type: methodId.split(':')[1],
            id: methodId.split(':')[2],
        };
    }

    function getReceiptEmail(methodId) {
        return $('.receipt-email-data.' + methodId.replace(/:/g, '_')).val();
    }

    function serializeForm(paymentMethodId, paymentSource) {
        paymentSource.method = paymentMethodId;

        // encode payment source into form
        const html = InvoicedBillingPortal.util.encodeToForm(paymentSource);
        $('#paymentSourceData').html(html);
        const submitForm = $('#paymentSubmitForm');

        return submitForm.serialize();
    }
})();
