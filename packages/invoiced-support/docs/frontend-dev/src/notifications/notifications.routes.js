(function () {
    'use strict';

    angular.module('app.notifications').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider.state('manage.notifications', {
            url: '/alerts',
            templateUrl: 'notifications/views/notifications.html',
            controller: 'UserNotificationsController',
        });
    }
})();
