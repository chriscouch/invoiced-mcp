(function () {
    'use strict';

    angular.module('app.integrations').factory('ReconciliationError', ReconciliationError);

    ReconciliationError.$inject = ['$resource', 'InvoicedConfig'];

    function ReconciliationError($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/reconciliation_errors/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                retry: {
                    url: InvoicedConfig.apiBaseUrl + '/reconciliation_errors/:id/retry',
                    method: 'POST',
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
