(function () {
    'use strict';

    angular.module('app.payment_plans').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.payment_plans', {
                abstract: true,
                url: '/payment_plans',
                template: '<ui-view/>',
            })
            .state('manage.payment_plans.browse', {
                url: '',
                templateUrl: 'payment_plans/views/browse.html',
                controller: 'BrowsePaymentPlansController',
                reloadOnSearch: false,
            });
    }
})();
