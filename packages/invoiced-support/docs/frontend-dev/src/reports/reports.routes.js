(function () {
    'use strict';

    angular.module('app.reports').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.reports', {
                url: '/reports',
                abstract: true,
                template: '<ui-view/>',
                resolve: {
                    allowed: allowed('reports.create'),
                },
            })
            .state('manage.reports.list', {
                url: '',
                templateUrl: 'reports/views/reports.html',
                controller: 'ReportsController',
            })
            .state('manage.reports.new', {
                url: '/new',
                templateUrl: 'reports/views/builder.html',
                controller: 'ReportBuilderController',
            })

            .state('manage.report', {
                abstract: true,
                url: '/reports/:id',
                template: '<ui-view/>',
                resolve: {
                    allowed: allowed('reports.create'),
                },
            })
            .state('manage.report.view', {
                url: '',
                templateUrl: 'reports/views/view.html',
                controller: 'ViewReportController',
            })
            .state('manage.report.edit', {
                url: '/edit',
                templateUrl: 'reports/views/builder.html',
                controller: 'ReportBuilderController',
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
