(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardApBalance', dashboardApBalance);

    function dashboardApBalance() {
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
                            name: 'A/P Balance',
                            description: '',
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

                        $scope.loading = true;
                        Dashboard.getMetricDebounced(
                            'ap_balance',
                            {},
                            function (result) {
                                $scope.loading = false;
                                $scope.generatedAt = result.generated_at;
                                $scope.metricValue = result.total_balance;
                                $scope.hasValue = true;

                                // calculate threshold after which we apply filter
                                let threshold = Math.pow(10, 7) - 1;
                                if ($scope.metricValue > threshold) {
                                    $scope.metricValueFormatted =
                                        Money.currencySymbol(result.currency) +
                                        $filter('metricNumber')($scope.metricValue);
                                } else {
                                    $scope.metricValueFormatted = Money.currencyFormat(
                                        $scope.metricValue,
                                        result.currency,
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
