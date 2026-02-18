(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardExpectedPayments', dashboardExpectedPayments);

    function dashboardExpectedPayments() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/metric.html',
            scope: {
                context: '=',
                options: '=',
            },
            controller: [
                '$scope',
                '$filter',
                'Dashboard',
                'Money',
                'selectedCompany',
                function ($scope, $filter, Dashboard, Money, selectedCompany) {
                    let loadedCurrency;

                    $scope.options = angular.extend(
                        {
                            name: 'Expected Payments',
                            description: 'Promise to Pays + AutoPay',
                            gauge: false,
                            min: 0,
                            max: 0,
                            gaugeOptions: {
                                percentColors: [
                                    [0.5, '#009E74'],
                                    [0.75, '#FFBF3E'],
                                    [1.0, '#E64A2E'],
                                ],
                            },
                        },
                        $scope.options,
                    );

                    function load(context) {
                        if (loadedCurrency === context.currency) {
                            return;
                        }

                        let params = { currency: context.currency };
                        if (typeof context.customer !== 'undefined') {
                            params.customer = context.customer;
                        }

                        $scope.loading = true;
                        Dashboard.getMetricDebounced(
                            'expected_payments',
                            params,
                            function (result) {
                                $scope.loading = false;
                                $scope.generatedAt = result.generated_at;
                                $scope.metricValue = result.total;
                                $scope.hasValue = true;

                                // calculate threshold after which we apply filter
                                let threshold = Math.pow(10, 7) - 1;
                                if ($scope.metricValue > threshold) {
                                    $scope.metricValueFormatted =
                                        Money.currencySymbol(context.currency) +
                                        $filter('metricNumber')($scope.metricValue);
                                } else {
                                    $scope.metricValueFormatted = Money.currencyFormat(
                                        $scope.metricValue,
                                        context.currency,
                                        selectedCompany.moneyFormat,
                                        true,
                                    );
                                }
                            },
                            function () {
                                $scope.loading = false;
                            },
                        );
                    }

                    $scope.$watch('context', load, true);
                },
            ],
        };
    }
})();
