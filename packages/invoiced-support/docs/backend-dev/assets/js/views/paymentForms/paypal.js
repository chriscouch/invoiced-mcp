/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    $(function () {
        $('.paypal-checkout-button').click(function (e) {
            e.preventDefault();

            var approve = $('#approvedCheckbox');
            var initials = $('.initials').val();
            if (approve.length && (approve.not(':checked').length || initials.length < 2)) {
                InvoicedBillingPortal.util.showError(
                    'You should agree with the terms of the estimate',
                    'paypal-errors'
                );
                return;
            }

            var params = $('#paypalFormParams input').serializeArray();
            var paramsObj = {};
            for (var i in params) {
                if (params.hasOwnProperty(i)) {
                    paramsObj[params[i].name] = params[i].value;
                }
            }
            var action = $('#paypalUrl').val();

            paramsObj.amount = InvoicedBillingPortal.util.getPaymentAmount();
            if (approve.length && initials) {
                paramsObj.notify_url += '&initials=' + initials;
            }
            if (paramsObj.currency_code === undefined) {
                paramsObj.currency_code = $('#currency_code').val();
                if (!paramsObj.currency_code) {
                    InvoicedBillingPortal.util.showError('Currency is not specified', 'paypal-errors');
                    return;
                }
            }
            // build a new paypal form, append it to the body, and submit it
            var paypalForm = $('<form action="' + action + '" method="post" target="_top"></form>');
            $('body').append(paypalForm);
            paypalForm.append(InvoicedBillingPortal.util.encodeToForm(paramsObj));
            paypalForm.submit();

            return false;
        });
    });
})();
