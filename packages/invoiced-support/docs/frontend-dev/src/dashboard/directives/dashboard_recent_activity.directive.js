(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardRecentActivity', dashboardRecentActivity);

    function dashboardRecentActivity() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/recent-activity.html',
            scope: {
                context: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.eventOptions = {
                        loadMore: false,
                        byDay: false,
                    };
                },
            ],
        };
    }
})();
