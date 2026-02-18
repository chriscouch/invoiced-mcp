(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('discountsBreakdown', discountsBreakdown);

    function discountsBreakdown() {
        return {
            restrict: 'E',
            template:
                '<a href="" ng-show="breakdown" tooltip="{{breakdown}}">' +
                '<span class="fas fa-info-circle"></span>' +
                '</a>',
            scope: {
                invoice: '=',
            },
            controller: [
                '$scope',
                '$filter',
                'Money',
                'selectedCompany',
                function ($scope, $filter, Money, selectedCompany) {
                    $scope.breakdown = false;

                    $scope.$watch('invoice', build);

                    let formatDate = $filter('formatCompanyDate');

                    function build(invoice) {
                        if (typeof invoice !== 'object') {
                            $scope.breakdown = false;
                            return;
                        }

                        let breakdown = [];

                        // add subtotal discounts
                        angular.forEach(invoice.discounts, function (discount) {
                            let line = '';

                            // name
                            if (discount.coupon) {
                                line += discount.coupon.name;
                            } else {
                                line += 'Discount';
                            }

                            // amount
                            line +=
                                ': ' +
                                Money.currencyFormat(discount.amount, invoice.currency, selectedCompany.moneyFormat);

                            // valid until
                            if (discount.expires) {
                                line += ' (expires ' + formatDate(discount.expires) + ')';
                            }

                            breakdown.push(line);
                        });

                        // mention line item discounts (if any)
                        // TODO this is broken if line items are not fully loaded
                        if (typeof invoice.items === 'object') {
                            let lineItemDiscounts = 0;
                            angular.forEach(invoice.items, function (item) {
                                angular.forEach(item.discounts, function (discount) {
                                    lineItemDiscounts += discount.amount;
                                });
                            });

                            if (lineItemDiscounts > 0) {
                                breakdown.push(
                                    'Line item discounts: ' +
                                        Money.currencyFormat(
                                            lineItemDiscounts,
                                            invoice.currency,
                                            selectedCompany.moneyFormat,
                                        ),
                                );
                            }
                        }

                        $scope.breakdown = breakdown.join(', ');
                    }
                },
            ],
        };
    }
})();
