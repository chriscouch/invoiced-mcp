(function () {
    'use strict';

    angular.module('app.developer_tools').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.webhook_attempts', {
                url: '/webhook_attempts',
                abstract: true,
                template: '<ui-view/>',
                resolve: {
                    allowed: allowed('business.admin'),
                },
            })
            .state('manage.webhook_attempts.browse', {
                url: '',
                templateUrl: 'developer_tools/views/browse-webhook-attempts.html',
                controller: 'BrowseWebhookAttemptsController',
            });
    }

    function allowed(permission) {
        return [
            '$q',
            'Permission',
            function ($q, Permission) {
                if (Permission.hasPermission(permission)) {
                    return true;
                }

                return $q.reject('Not Authorized');
            },
        ];
    }
})();
