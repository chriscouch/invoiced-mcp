(function () {
    'use strict';

    angular.module('app.developer_tools').factory('Webhook', Webhook);

    Webhook.$inject = ['$resource', 'InvoicedConfig'];

    function Webhook($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/webhooks/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                create: {
                    method: 'POST',
                },
                edit: {
                    method: 'PATCH',
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
