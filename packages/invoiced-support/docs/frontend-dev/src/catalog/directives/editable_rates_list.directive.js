(function () {
    'use strict';

    angular.module('app.catalog').directive('editableRatesList', editableRatesList);

    function editableRatesList() {
        return {
            restrict: 'E',
            templateUrl: 'catalog/views/editable-rates-list.html',
            scope: {
                line: '=',
                currency: '=',
                options: '=?',
            },
            controller: [
                '$scope',
                'selectedCompany',
                'Money',
                function ($scope, selectedCompany, Money) {
                    $scope.options = $scope.options || {};
                    let options = {
                        types: ['discounts', 'taxes', 'shipping'],
                    };
                    angular.extend(options, $scope.options);
                    $scope.options = options;

                    $scope.value = rateValue;
                    $scope.delete = deleteRate;

                    let rateObjectKeys = {
                        discounts: 'coupon',
                        taxes: 'tax_rate',
                        shipping: 'shipping_rate',
                    };

                    function rateValue(appliedRate, type) {
                        if (typeof appliedRate === 'undefined') {
                            return '';
                        }

                        // check if this is a Rate object
                        // instead of an Applied Rate
                        let k = rateObjectKeys[type];
                        if (!k || typeof appliedRate[k] === 'undefined') {
                            // if it is a Rate object then convert
                            // it to an Applied Rate with a Rate attached
                            let _appliedRate = {};
                            _appliedRate[k] = appliedRate;
                            appliedRate = _appliedRate;
                        }

                        let rate = appliedRate[k];
                        if (rate) {
                            // i) Rate attached
                            if (rate.is_percent) {
                                return rate.value + '%';
                            } else {
                                return Money.currencyFormat(rate.value, rate.currency, selectedCompany.moneyFormat);
                            }
                        } else {
                            // ii) Custom amount
                            return Money.currencyFormat(
                                appliedRate.amount,
                                $scope.currency,
                                selectedCompany.moneyFormat,
                            );
                        }
                    }

                    function deleteRate(appliedRate, type) {
                        let rates;
                        if (type) {
                            rates = $scope.line[type];
                        } else {
                            rates = $scope.line;
                        }

                        for (let i in rates) {
                            if (angular.equals(rates[i], appliedRate)) {
                                rates.splice(i, 1);
                                break;
                            }
                        }
                    }
                },
            ],
        };
    }
})();
