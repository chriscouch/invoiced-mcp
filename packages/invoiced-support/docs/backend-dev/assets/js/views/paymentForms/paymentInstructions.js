/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    if (typeof window.offlinePaymentsInitialized !== 'undefined') {
        return;
    }
    window.offlinePaymentsInitialized = true;

    $(function () {
        const dateEls = $('.expected-date');
        dateEls.each((i, el) => {
            const dateEl = $(el);
            const dateElId = dateEl.attr('id');
            dateEl.datepicker({
                minDate: 0,
                maxDate: '+3M',
                altField: '#' + dateElId + 'Alt',
                altFormat: 'yy-mm-dd',
            });
            const currentLanguage = InvoicedBillingPortal.util.getCurrentLanguage();
            if (typeof $.datepicker.regional[currentLanguage.code] !== 'undefined') {
                dateEl.datepicker('option', $.datepicker.regional[currentLanguage.code]);
            }
        });

        $('.offline-payment-button').click(function (e) {
            e.preventDefault();

            var btn = $(this);
            btn.attr('disabled', 'disabled');

            var paymentMethodId = $(this).data('payment-method');
            var parent = $('.payment-method-form.' + paymentMethodId);

            const paymentSource = {
                date: $('.expected-date', parent).val(),
                reference: $('.expected-date-reference', parent).val(),
                notes: $('.expected-date-notes', parent).val(),
            };

            InvoicedBillingPortal.payments.submit(
                paymentSource,
                function () {
                    // success
                },
                function () {
                    // fail
                    btn.removeAttr('disabled');
                }
            );

            return false;
        });
    });
})();
