/* globals moment */
(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardTimeToPay', dashboardTimeToPay);

    function dashboardTimeToPay() {
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
                function ($scope, $filter, Dashboard) {
                    let loadedCurrency;

                    $scope.options = angular.extend(
                        {
                            name: 'Time to Pay',
                            description: 'Average time for invoices to be paid over past year',
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
                            'time_to_pay',
                            params,
                            function (result) {
                                $scope.loading = false;

                                $scope.generatedAt = result.generated_at;
                                $scope.metricValue = result.average_time_to_pay;
                                $scope.hasValue = $scope.metricValue >= 0;
                                $scope.metricValueFormatted =
                                    $filter('number')($scope.metricValue) +
                                    ' day' +
                                    ($scope.metricValue != 1 ? 's' : '');

                                // Calculate change from previous period
                                $scope.change = 0;
                                Dashboard.getMetricDebounced(
                                    'time_to_pay',
                                    {
                                        currency: context.currency,
                                        end_date: moment().subtract(1, 'months').endOf('month').format('YYYY-MM-DD'),
                                    },
                                    function (result2) {
                                        if (result2.average_time_to_pay >= 0) {
                                            let delta = result.average_time_to_pay - result2.average_time_to_pay;
                                            if (delta !== 0 && result2.average_time_to_pay !== 0) {
                                                $scope.change = Math.round((delta / result2.average_time_to_pay) * 100);
                                                $scope.changeAbs = Math.abs($scope.change);
                                            }
                                        }
                                    },
                                    function () {
                                        // do nothing on failure
                                    },
                                );
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
