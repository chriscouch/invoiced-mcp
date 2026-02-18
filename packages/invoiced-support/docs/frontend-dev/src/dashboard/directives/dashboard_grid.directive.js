(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardGrid', dashboardGrid);

    function dashboardGrid() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/grid.html',
            scope: {
                context: '=',
                grid: '=',
            },
        };
    }
})();
