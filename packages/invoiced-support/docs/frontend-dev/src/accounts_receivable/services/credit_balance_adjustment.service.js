(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('CreditBalanceAdjustment', CreditBalanceAdjustment);

    CreditBalanceAdjustment.$inject = ['$resource', 'InvoicedConfig'];

    function CreditBalanceAdjustment($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/credit_balance_adjustments/:id',
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
