(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('Card', Card);

    Card.$inject = ['$resource', 'InvoicedConfig'];

    function Card($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/cards/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                start: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/cards/setup_intent',
                },
                finish: {
                    method: 'POST',
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
