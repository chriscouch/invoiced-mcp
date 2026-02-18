(function () {
    'use strict';

    angular.module('app.collections').factory('ChasingCadence', ChasingCadence);

    ChasingCadence.$inject = ['$resource', 'InvoicedConfig'];

    function ChasingCadence($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/chasing_cadences/:id',
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
                run: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/chasing_cadences/:id/runs',
                },
                assign: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/chasing_cadences/:id/assign',
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
