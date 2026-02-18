(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardActivityChart', dashboardActivityChart);

    function dashboardActivityChart() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/activity-chart.html',
            scope: {
                context: '=',
            },
        };
    }
})();
