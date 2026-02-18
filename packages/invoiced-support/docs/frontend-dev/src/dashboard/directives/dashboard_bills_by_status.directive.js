(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardBillsByStatus', dashboardBillsByStatus);

    function dashboardBillsByStatus() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/bills-by-status.html',
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

                    function load(context) {
                        if (loadedCurrency === context.currency) {
                            return;
                        }

                        $scope.loading = true;
                        Dashboard.getMetricDebounced(
                            'bills_by_status',
                            { currency: context.currency },
                            function (result) {
                                $scope.loading = false;
                                $scope.currency = result.currency;
                                $scope.generatedAt = result.generated_at;
                                $scope.byStatus = result.by_status;
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
