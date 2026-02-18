(function () {
    'use strict';

    angular.module('app.search').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider.state('manage.search', {
            url: '/search?q',
            templateUrl: 'search/views/search.html',
            controller: 'SearchController',
            params: {
                q: null,
                object: null,
            },
        });
    }
})();
