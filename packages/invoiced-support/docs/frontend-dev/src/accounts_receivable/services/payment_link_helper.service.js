(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('PaymentLinkHelper', PaymentLinkHelper);

    PaymentLinkHelper.$inject = ['Money', 'selectedCompany'];

    function PaymentLinkHelper(Money, selectedCompany) {
        return {
            calculateTotalPrice: calculateTotalPrice,
            getFormattedPrice: getFormattedPrice,
        };

        function getFormattedPrice(paymentLink) {
            const price = calculateTotalPrice(paymentLink);
            if (price > 0) {
                const currency = paymentLink.currency || selectedCompany.currency;

                return Money.currencyFormat(price, currency, selectedCompany.moneyFormat);
            }

            return '';
        }

        function calculateTotalPrice(paymentLink) {
            let total = 0;
            angular.forEach(paymentLink.items, function (lineItem) {
                if (!isNaN(lineItem.amount)) {
                    total += lineItem.amount;
                }
            });

            return total;
        }
    }
})();
