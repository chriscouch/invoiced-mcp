(function () {
    'use strict';

    angular.module('app.search').factory('Search', Search);

    Search.$inject = ['$resource', 'InvoicedConfig'];

    function Search($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/search',
            {},
            {
                search: {
                    method: 'GET',
                    isArray: true,
                },
            },
        );
    }
})();
