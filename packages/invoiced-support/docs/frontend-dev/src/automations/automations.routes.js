(function () {
    'use strict';

    angular.module('app.automations').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.automations', {
                url: '/automations',
                abstract: true,
                template: '<ui-view/>',
            })
            .state('manage.automations.new_workflow', {
                url: '/workflows/new',
                templateUrl: 'automations/views/edit-workflow.html',
                controller: 'EditAutomationWorkflowController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.automations.edit_workflow', {
                url: '/workflows/:id',
                templateUrl: 'automations/views/edit-workflow.html',
                controller: 'EditAutomationWorkflowController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.automations.duplicate_workflow', {
                url: '/workflows/:id/duplicate',
                templateUrl: 'automations/views/edit-workflow.html',
                controller: 'EditAutomationWorkflowController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.automations.builder', {
                url: '/workflows/:id/builder',
                templateUrl: 'automations/views/automation-builder.html',
                controller: 'AutomationBuilderController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
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
