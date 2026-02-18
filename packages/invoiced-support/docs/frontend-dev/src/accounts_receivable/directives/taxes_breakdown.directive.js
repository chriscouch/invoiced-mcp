(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('taxesBreakdown', taxesBreakdown);

    function taxesBreakdown() {
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
                'Core',
                function ($scope, Money, selectedCompany, Core) {
                    $scope.breakdown = false;

                    $scope.$watch('invoice', build);

                    function build(invoice) {
                        if (typeof invoice !== 'object') {
                            $scope.breakdown = false;
                            return;
                        }

                        let breakdown = [];

                        // add subtotal taxes
                        angular.forEach(invoice.taxes, function (tax) {
                            let line = '';

                            // name
                            if (tax.tax_rate) {
                                line += tax.tax_rate.name;
                            } else {
                                line += Core.taxLabelForCountry(selectedCompany.country);
                            }

                            // amount
                            line +=
                                ': ' + Money.currencyFormat(tax.amount, invoice.currency, selectedCompany.moneyFormat);

                            breakdown.push(line);
                        });

                        // mention line item taxes (if any)
                        // TODO this is broken if line items are not fully loaded
                        if (typeof invoice.items === 'object') {
                            let lineItemTaxes = 0;
                            angular.forEach(invoice.items, function (item) {
                                angular.forEach(item.taxes, function (tax) {
                                    lineItemTaxes += tax.amount;
                                });
                            });

                            if (lineItemTaxes > 0) {
                                breakdown.push(
                                    'Line item taxes: ' +
                                        Money.currencyFormat(
                                            lineItemTaxes,
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
