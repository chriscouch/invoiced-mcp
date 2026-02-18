(function () {
    'use strict';

    angular.module('app.developer_tools').factory('ApiKey', ApiKey);

    ApiKey.$inject = ['$resource', 'InvoicedConfig'];

    function ApiKey($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/api_keys/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                find: {
                    method: 'GET',
                },
                create: {
                    method: 'POST',
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
