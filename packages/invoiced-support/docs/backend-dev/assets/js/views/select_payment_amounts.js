/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    InvoicedBillingPortal.bootstrap(run);

    function run() {
        $('.item-amount-to-pay').change(function () {
            updateAmounts($(this).parents('.payment-item'));
        });

        $('.payment-item').each(function () {
            updateAmounts($(this));
        });

        function updateAmounts(rowEl) {
            var selector = $('.item-amount-to-pay', rowEl);
            var amount;
            if (selector.is('select')) {
                amount = selector.find('option:selected').data('amount');
            } else {
                amount = selector.data('amount');
            }

            var amountInput = $('.item-amount-input', rowEl);
            var amountDisplay = $('.item-amount-static', rowEl);
            if (amount) {
                amountDisplay.removeClass('hidden').html(amount);
                amountInput.addClass('hidden');
            } else {
                amountDisplay.addClass('hidden');
                amountInput.removeClass('hidden');
            }
        }
    }
})();
