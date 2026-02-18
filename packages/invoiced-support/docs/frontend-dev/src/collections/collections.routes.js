(function () {
    'use strict';

    angular.module('app.collections').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.collections', {
                url: '/collections',
                abstract: true,
                template: '<ui-view/>',
            })
            .state('manage.collections.new_chasing_cadence', {
                url: '/cadences/new',
                templateUrl: 'collections/views/edit-cadence.html',
                controller: 'EditChasingCadenceController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.collections.edit_chasing_cadence', {
                url: '/cadences/:id',
                templateUrl: 'collections/views/edit-cadence.html',
                controller: 'EditChasingCadenceController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.collections.duplicate_chasing_cadence', {
                url: '/cadences/:id/duplicate',
                templateUrl: 'collections/views/edit-cadence.html',
                controller: 'EditChasingCadenceController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.collections.new_invoice_chasing_cadence', {
                url: '/invoice_cadences/new',
                templateUrl: 'collections/views/edit-invoice-cadence.html',
                controller: 'EditInvoiceChasingCadenceController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.collections.edit_invoice_chasing_cadence', {
                url: '/invoice_cadences/:id',
                templateUrl: 'collections/views/edit-invoice-cadence.html',
                controller: 'EditInvoiceChasingCadenceController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.collections.duplicate_invoice_chasing_cadence', {
                url: '/invoice_cadences/:id/duplicate',
                templateUrl: 'collections/views/edit-invoice-cadence.html',
                controller: 'EditInvoiceChasingCadenceController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.activities', {
                url: '/activities',
                abstract: true,
                template: '<ui-view/>',
            })
            .state('manage.activities.browse', {
                url: '',
                templateUrl: 'collections/views/tasks/browse.html',
                controller: 'BrowseTasksController',
            });
    }

    function allowed(permission) {
        return [
            'userBootstrap',
            '$q',
            'Permission',
            function (userBootstrap, $q, Permission) {
                if (Permission.hasPermission(permission)) {
                    return true;
                }

                return $q.reject('Not Authorized');
            },
        ];
    }
})();
