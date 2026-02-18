(function () {
    'use strict';

    angular.module('app.catalog').directive('taxRates', taxRates);

    function taxRates() {
        return {
            restrict: 'E',
            templateUrl: 'catalog/views/tax-rates.html',
            scope: {
                taxes: '=',
                currency: '=',
            },
            controller: [
                '$scope',
                'selectedCompany',
                'Money',
                function ($scope, selectedCompany, Money) {
                    $scope.value = function (tax) {
                        if (typeof tax === 'undefined') {
                            return '';
                        }

                        if (tax.is_percent) {
                            return tax.value + '%';
                        } else {
                            return Money.currencyFormat(tax.value, tax.currency, selectedCompany.moneyFormat);
                        }
                    };
                },
            ],
        };
    }
})();
