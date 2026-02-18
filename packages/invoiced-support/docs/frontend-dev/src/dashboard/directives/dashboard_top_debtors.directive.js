(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardTopDebtors', dashboardTopDebtors);

    function dashboardTopDebtors() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/top-debtors.html',
            scope: {
                context: '=',
            },
            controller: [
                '$scope',
                'Dashboard',
                function ($scope, Dashboard) {
                    let loadedCurrency;
                    $scope.topDebtors = [];

                    function load(context) {
                        if (loadedCurrency === context.currency) {
                            return;
                        }

                        $scope.loading = true;
                        Dashboard.getMetricDebounced(
                            'top_debtors',
                            { currency: context.currency },
                            function (topDebtors) {
                                $scope.loading = false;
                                loadedCurrency = context.currency;

                                $scope.topDebtors = topDebtors.top_debtors;

                                // Max Outstanding Balance
                                $scope.outstandingMax = 0;
                                angular.forEach($scope.topDebtors, function (account) {
                                    account.currency = context.currency;
                                    account.total = account.balance;
                                    if (account.balance > $scope.outstandingMax) {
                                        $scope.outstandingMax = account.balance;
                                    }
                                });
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
