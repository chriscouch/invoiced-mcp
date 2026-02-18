(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardDaysSalesOutstanding', dashboardDaysSalesOutstanding);

    function dashboardDaysSalesOutstanding() {
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
                            name: 'DSO',
                            description: 'Accounts Receivable / Past Year Total Sales x 365',
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
                            'days_sales_outstanding',
                            params,
                            function (dashboard) {
                                $scope.loading = false;
                                $scope.generatedAt = dashboard.generated_at;
                                $scope.metricValue = dashboard.dso;
                                $scope.hasValue = $scope.metricValue >= 0;
                                $scope.metricValueFormatted =
                                    $filter('number')($scope.metricValue) +
                                    ' day' +
                                    ($scope.metricValue != 1 ? 's' : '');
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
