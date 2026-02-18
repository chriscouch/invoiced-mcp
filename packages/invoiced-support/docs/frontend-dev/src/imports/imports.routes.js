(function () {
    'use strict';

    angular.module('app.imports').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.imports', {
                url: '/imports',
                abstract: true,
                template: '<ui-view/>',
            })
            .state('manage.imports.browse', {
                url: '',
                templateUrl: 'imports/views/browse.html',
                controller: 'BrowseImportsController',
            })

            .state('manage.imports.start', {
                url: '/start',
                abstract: true,
                template: '<ui-view/>',
            })
            .state('manage.imports.start.payment', {
                url: '/payment',
                templateUrl: 'imports/views/choices.html',
                controller: 'ImportChoicesController',
                resolve: {
                    allowed: allowed(['imports.create', 'payments.create']),
                    title: function () {
                        return 'Import Payments';
                    },
                    choices: function () {
                        return [
                            {
                                name: 'Spreadsheet',
                                iconClass: 'icon spreadsheet-icon',
                                route: "manage.imports.new.spreadsheet({type:'payment'})",
                            },
                            {
                                name: 'Bank Feed Transaction (BAI File)',
                                iconClass: 'icon bai-icon',
                                route: 'manage.imports.new.bank_feed_transaction_bai',
                                feature: 'cash_application',
                            },
                        ];
                    },
                },
            })

            .state('manage.imports.new', {
                abstract: true,
                url: '/new',
                template: '<ui-view/>',
                resolve: {
                    allowed: allowed('imports.create'),
                },
            })
            .state('manage.imports.new.step1', {
                url: '',
                controller: 'StartImportController',
                templateUrl: 'imports/views/new.html',
                resolve: {
                    title: function () {
                        return 'New Import';
                    },
                },
            })
            .state('manage.imports.new.spreadsheet', {
                url: '/spreadsheet/:type',
                templateUrl: 'imports/views/new-spreadsheet.html',
                controller: 'NewSpreadsheetImportController',
            })
            .state('manage.imports.new.bank_feed_transaction_bai', {
                url: '/bai',
                templateUrl: 'imports/views/new-bai-file.html',
                controller: 'NewBAIPaymentImportController',
            })

            .state('manage.import', {
                abstract: true,
                url: '/imports/:id',
                template: '<ui-view/>',
            })
            .state('manage.import.view', {
                url: '',
                templateUrl: 'imports/views/view.html',
                controller: 'ViewImportController',
            });
    }

    function allowed(permission) {
        return [
            'userBootstrap',
            '$q',
            'Permission',
            function (userBootstrap, $q, Permission) {
                if (typeof permission === 'object' && permission instanceof Array) {
                    for (let i in permission) {
                        let _permission = permission[i];
                        if (!Permission.hasPermission(_permission)) {
                            return $q.reject('Not Authorized');
                        }
                    }

                    return true;
                }

                if (Permission.hasPermission(permission)) {
                    return true;
                }

                return $q.reject('Not Authorized');
            },
        ];
    }
})();
