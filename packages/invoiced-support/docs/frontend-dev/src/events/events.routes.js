(function () {
    'use strict';

    angular.module('app.events').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.events', {
                abstract: true,
                url: '/events',
                template: '<ui-view/>',
            })
            .state('manage.events.browse', {
                url: '',
                templateUrl: 'events/views/browse.html',
                controller: 'BrowseEventsController',
                reloadOnSearch: false,
                resolve: {
                    allowed: [
                        'userBootstrap',
                        '$q',
                        'Permission',
                        'selectedCompany',
                        function (userBootstrap, $q, Permission, selectedCompany) {
                            if (!Permission.hasPermission('reports.create')) {
                                return $q.reject('Not Authorized');
                            } else if (
                                selectedCompany.restriction_mode === 'owner' ||
                                selectedCompany.restriction_mode === 'custom_field'
                            ) {
                                return $q.reject('Not Authorized');
                            }

                            return true;
                        },
                    ],
                },
            })
            .state('manage.event', {
                abstract: true,
                url: '/events/:id',
                template: '<ui-view/>',
            })
            .state('manage.event.view', {
                url: '',
                templateUrl: 'events/views/view.html',
                controller: 'ViewEventController',
            });
    }
})();
