(function () {
    'use strict';

    angular.module('app.collections').factory('InvoiceChasingCadence', InvoiceChasingCadence);

    InvoiceChasingCadence.$inject = ['$resource', 'InvoicedConfig'];

    function InvoiceChasingCadence($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/invoice_chasing_cadences/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        sort: 'name ASC',
                    },
                },
                find: {
                    method: 'GET',
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
