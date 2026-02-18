(function () {
    'use strict';

    angular.module('app.settings').factory('CashApplicationRules', CashApplicationRules);

    CashApplicationRules.$inject = ['$resource', 'InvoicedConfig'];

    function CashApplicationRules($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/cash_application/rules/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        include: 'customerName',
                    },
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
