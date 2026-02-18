(function () {
    'use strict';

    angular.module('app.dashboard').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider.state('manage.dashboard', {
            url: '/dashboard',
            templateUrl: 'dashboard/views/dashboard.html',
            controller: 'DashboardController',
        });
    }
})();
