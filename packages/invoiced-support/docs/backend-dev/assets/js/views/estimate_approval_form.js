/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    InvoicedBillingPortal.bootstrap(run);

    function run() {
        var sending = false;
        //prevents from showing warning on page start
        var convenienceFeeEl = $('#convenience-fee-warning').hide().removeClass('hidden');

        $('#approvedCheckbox')
            .change(function () {
                if ($(this).is(':checked')) {
                    $('.initials-holder').removeClass('hidden');
                } else {
                    $('.initials-holder').addClass('hidden');
                }
            })
            .change();

        // payment mode
        $('[name=payment_mode],[name=payment_method]').change(selectedPaymentMethod);
        selectedPaymentMethod();

        $('#approvalForm').submit(function (e) {
            if (sending) {
                return;
            }

            e.preventDefault();

            var paymentMode = getType();
            var paymentMethod = getMethod();
            var noPayment = $('.payment-info-form.' + paymentMode).length === 0;
            if (noPayment || !InvoicedBillingPortal.payments.isRegistered(paymentMethod)) {
                sending = true;
                payment(
                    function () {
                        sending = false;
                    },
                    function () {
                        sending = false;
                    }
                );
                return;
            }

            // capture the payment information
            InvoicedBillingPortal.util.showLoading($('#informationBeingSavedMessage').text());
            InvoicedBillingPortal.payments.capture(paymentMethod, {}, function (paymentSource) {
                // encode payment source into form
                var html = InvoicedBillingPortal.util.encodeToForm(paymentSource, 'payment_source');
                $('.payment-source-data').html(html);

                sending = true;
                payment(
                    function () {
                        sending = false;
                    },
                    function () {
                        sending = false;
                    }
                );
            });
        });

        function payment(resolve, reject) {
            // NOTE: not using promises here because they
            // are not supported in IE
            var url = $('#approvalForm').attr('action');
            $.ajax({
                method: 'POST',
                url: url,
                data: $('#approvalForm').serialize(),
                headers: {
                    Accept: 'application/json',
                },
            })
                .then(function (data) {
                    window.location.href = data.url;
                    resolve();
                })
                .fail(function (data) {
                    // show the error message
                    var message;
                    try {
                        message = JSON.parse(data.responseText).error;
                    } catch (err) {
                        message = 'An unknown error has occurred';
                    }
                    InvoicedBillingPortal.util.showError(message, 'estimate-approval-errors');
                    reject();
                });
        }

        function selectedPaymentMethod() {
            var type = getType();
            $('.payment-mode').addClass('hidden');
            $('.payment-info-form').addClass('hidden');
            var approveBtn = $('#approve-button-row');
            if (!$('#autopay-enrolled').val()) {
                approveBtn.addClass('hidden');
            }
            convenienceFeeEl.hide();

            if (!type) {
                return;
            }

            var modeEl = $('.payment-mode.' + type);
            modeEl.removeClass('hidden');
            modeEl.find('.radio-default').prop('checked', true);
            var method = getMethod();
            if (!method) {
                return;
            }
            if (method !== 'paypal') {
                approveBtn.removeClass('hidden');
            } else {
                approveBtn.addClass('hidden');
            }
            $('.payment-info-form.' + type + '.' + method).removeClass('hidden');

            if (method === 'credit_card' || method.indexOf('card:') !== -1) {
                convenienceFeeEl.show();
            }
        }

        function getType() {
            return $('[name=payment_mode]:checked').val();
        }

        function getMethod() {
            return (
                $('.payment-mode:visible [name=payment_method]:checked').val() ||
                $('.payment-mode:visible [name=payment_method].radio-default').val()
            );
        }
    }
})();
