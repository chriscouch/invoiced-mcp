(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardActionItems', dashboardActionItems);

    function dashboardActionItems() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/action-items.html',
            scope: {
                context: '=',
            },
            controller: [
                '$scope',
                'Dashboard',
                function ($scope, Dashboard) {
                    $scope.actionItems = { count: 0 };
                    $scope.loading = true;
                    Dashboard.getMetricDebounced(
                        'action_items',
                        {},
                        function (actionItems) {
                            $scope.loading = false;
                            $scope.actionItems = actionItems;
                        },
                        function () {
                            $scope.loading = false;
                        },
                    );
                },
            ],
        };
    }
})();
