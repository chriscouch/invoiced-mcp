(function () {
    'use strict';

    angular.module('app.subscriptions').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.subscriptions', {
                abstract: true,
                url: '/subscriptions',
                template: '<ui-view/>',
            })
            .state('manage.subscriptions.browse', {
                url: '',
                templateUrl: 'subscriptions/views/browse.html',
                controller: 'BrowseSubscriptionsController',
            })
            .state('manage.subscription', {
                abstract: true,
                url: '/subscriptions/:id',
                template: '<ui-view/>',
            })
            .state('manage.subscription.view', {
                abstract: true,
                url: '',
                templateUrl: 'subscriptions/views/view.html',
                controller: 'ViewSubscriptionController',
            })
            .state('manage.subscription.view.summary', {
                url: '',
                templateUrl: 'subscriptions/views/summary.html',
                controller: [
                    '$scope',
                    function ($scope) {
                        $scope.loadInvoices($scope.modelId);
                    },
                ],
            })
            .state('manage.subscription.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            });
    }
})();
