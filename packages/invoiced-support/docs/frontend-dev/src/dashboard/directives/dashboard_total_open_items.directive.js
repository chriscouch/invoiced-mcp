(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardTotalOpenItems', dashboardTotalOpenItems);

    function dashboardTotalOpenItems() {
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
                            name: 'Open Items',
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
                            'ar_balance',
                            { currency: context.currency },
                            function (dashboard) {
                                $scope.loading = false;
                                $scope.generatedAt = dashboard.generated_at;
                                $scope.metricValue = dashboard.num_open_items;
                                $scope.hasValue = $scope.metricValue >= 0;
                                $scope.metricValueFormatted = $filter('number')($scope.metricValue);
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
