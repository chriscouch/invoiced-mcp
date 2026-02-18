(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardDaysPayableOutstanding', dashboardDaysPayableOutstanding);

    function dashboardDaysPayableOutstanding() {
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
                            name: 'DPO',
                            description: 'Accounts Payable / Past Year Total Purchases x 365',
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
                            'days_payable_outstanding',
                            { currency: context.currency },
                            function (dashboard) {
                                $scope.loading = false;
                                $scope.generatedAt = dashboard.generated_at;
                                $scope.metricValue = dashboard.dpo;
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
