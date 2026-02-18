(function () {
    'use strict';

    angular.module('app.components').directive('currencySelector', currencySelector);

    function currencySelector() {
        let currencies;

        return {
            restrict: 'E',
            template:
                '<div class="invoiced-select">' +
                '<select ng-model="model" ng-options="c.name as c.label for c in currencies" tabindex="{{tabindex}}" ng-disabled="disabled"></select>' +
                '</div>',
            scope: {
                model: '=ngModel',
                tabindex: '=?ngTabindex',
                disabled: '=?ngDisabled',
                available: '=?',
            },
            controller: [
                'InvoicedConfig',
                '$scope',
                function (InvoicedConfig, $scope) {
                    if (!currencies) {
                        currencies = [];
                        for (let i in InvoicedConfig.currencies) {
                            let c = InvoicedConfig.currencies[i];
                            if (c.symbol) {
                                currencies.push({
                                    name: i,
                                    label: i.toUpperCase() + ' - ' + c.symbol,
                                });
                            }
                        }
                    }

                    if ($scope.available) {
                        $scope.currencies = [];
                        angular.forEach(currencies, function (currency) {
                            if ($scope.available.indexOf(currency.name) !== -1) {
                                $scope.currencies.push(currency);
                            }
                        });
                    } else {
                        $scope.currencies = currencies;
                    }
                },
            ],
        };
    }
})();
