(function () {
    'use strict';

    angular.module('app.content').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.announcements', {
                url: '/announcements',
                templateUrl: 'content/views/announcements.html',
                controller: 'AnnouncementsController',
            })
            .state('manage.help', {
                url: '/help',
                templateUrl: 'content/views/help.html',
                controller: 'HelpController',
            });
    }
})();
