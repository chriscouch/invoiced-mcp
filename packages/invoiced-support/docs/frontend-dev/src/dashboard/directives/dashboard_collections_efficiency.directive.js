(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardCollectionsEfficiency', dashboardCollectionsEfficiency);

    function dashboardCollectionsEfficiency() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/metric.html',
            scope: {
                context: '=',
                options: '=',
            },
            controller: [
                '$scope',
                'Dashboard',
                function ($scope, Dashboard) {
                    let loadedCurrency;

                    $scope.options = angular.extend(
                        {
                            gauge: false,
                            min: 0,
                            max: 0,
                            name: 'CEI',
                            description: 'Ratio of invoices that have been collected over past year',
                            gaugeOptions: {
                                percentColors: [
                                    [0.75, '#E64A2E'],
                                    [0.9, '#FFBF3E'],
                                    [1.0, '#009E74'],
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
                            'collections_efficiency',
                            params,
                            function (dashboard) {
                                $scope.loading = false;
                                $scope.generatedAt = dashboard.generated_at;
                                $scope.metricValue = Math.round(dashboard.collections_efficiency * 100);
                                $scope.hasValue = $scope.metricValue >= 0;
                                $scope.metricValueFormatted = $scope.metricValue + '%';
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
