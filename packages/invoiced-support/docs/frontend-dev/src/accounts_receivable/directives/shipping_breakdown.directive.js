(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('shippingBreakdown', shippingBreakdown);

    function shippingBreakdown() {
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
                'Money',
                'selectedCompany',
                function ($scope, Money, selectedCompany) {
                    $scope.breakdown = false;

                    $scope.$watch('invoice', build);

                    function build(invoice) {
                        if (typeof invoice !== 'object') {
                            $scope.breakdown = false;
                            return;
                        }

                        let breakdown = [];

                        // add subtotal shipping
                        angular.forEach(invoice.shipping, function (shipping) {
                            let line = '';

                            // name
                            if (shipping.shipping_rate) {
                                line += shipping.shipping_rate.name;
                            } else {
                                line += 'Shipping';
                            }

                            // amount
                            line +=
                                ': ' +
                                Money.currencyFormat(shipping.amount, invoice.currency, selectedCompany.moneyFormat);

                            breakdown.push(line);
                        });

                        $scope.breakdown = breakdown.join(', ');
                    }
                },
            ],
        };
    }
})();
