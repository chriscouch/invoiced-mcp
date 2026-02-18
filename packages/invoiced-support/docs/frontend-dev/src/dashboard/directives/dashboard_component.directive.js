(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardComponent', dashboardComponent);

    function dashboardComponent() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/entry-point.html',
            scope: {
                type: '=',
                context: '=',
                options: '=?',
            },
        };
    }
})();
