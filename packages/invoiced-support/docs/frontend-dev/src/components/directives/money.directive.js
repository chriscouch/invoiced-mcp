(function () {
    'use strict';

    angular.module('app.components').directive('money', money);

    function money() {
        return {
            restrict: 'E',
            template: '<span class="money" ng-bind-html="money(amount, currency, filter)"></span>',
            scope: {
                currency: '=',
                amount: '=',
                filter: '=',
                precision: '=',
            },
            controller: [
                '$scope',
                '$filter',
                'selectedCompany',
                'Money',
                function ($scope, $filter, selectedCompany, Money) {
                    $scope.money = function (amount, currency, filter) {
                        let options = $scope.precision !== null ? { precision: $scope.precision } : {};
                        options = angular.extend(options, selectedCompany.moneyFormat);
                        if (filter !== undefined) {
                            //calculate threshold after which we apply filter
                            let threshold = Math.pow(10, filter) - 1;
                            if (amount > threshold) {
                                amount = $filter('metricNumber')(amount);
                                return Money.currencySymbol(currency) + amount;
                            }
                        }

                        return Money.currencyFormat(amount, currency, options, true);
                    };
                },
            ],
        };
    }
})();
