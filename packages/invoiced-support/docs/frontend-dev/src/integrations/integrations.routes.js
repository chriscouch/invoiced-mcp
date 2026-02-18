(function () {
    'use strict';

    angular.module('app.integrations').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider

            // Flywire Payments
            .state('manage.flywire_payments', {
                abstract: true,
                url: '/flywire/payments',
                template: '<ui-view/>',
            })
            .state('manage.flywire_payments.browse', {
                url: '',
                templateUrl: 'components/views/table-view.html',
                controller: 'BrowseFlywirePaymentsController',
                reloadOnSearch: false,
            })

            // Flywire Refunds
            .state('manage.flywire_refunds', {
                abstract: true,
                url: '/flywire/refunds',
                template: '<ui-view/>',
            })
            .state('manage.flywire_refunds.browse', {
                url: '',
                templateUrl: 'components/views/table-view.html',
                controller: 'BrowseFlywireRefundsController',
                reloadOnSearch: false,
            })

            // Flywire Disbursements
            .state('manage.flywire_disbursements', {
                abstract: true,
                url: '/flywire/disbursements',
                template: '<ui-view/>',
            })
            .state('manage.flywire_disbursements.browse', {
                url: '',
                templateUrl: 'components/views/table-view.html',
                controller: 'BrowseFlywireDisbursementsController',
                reloadOnSearch: false,
            })
            .state('manage.flywire_disbursement', {
                abstract: true,
                url: '/flywire/disbursements/:id',
                template: '<ui-view/>',
            })
            .state('manage.flywire_disbursement.view', {
                url: '',
                templateUrl: 'integrations/views/flywire/view-disbursement.html',
                controller: 'ViewFlywireDisbursementController',
            });
    }
})();
