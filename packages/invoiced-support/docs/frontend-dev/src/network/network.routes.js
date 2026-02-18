(function () {
    'use strict';

    angular.module('app.network').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            // Documents
            .state('manage.document', {
                abstract: true,
                url: '/documents/:id',
                template: '<ui-view/>',
            })
            .state('manage.document.view', {
                abstract: true,
                url: '',
                templateUrl: 'network/views/documents/view.html',
                controller: 'ViewNetworkDocumentController',
            })
            .state('manage.document.view.summary', {
                url: '',
                templateUrl: 'network/views/documents/summary.html',
            });
    }
})();
