(function () {
    'use strict';

    angular.module('app.settings').factory('CashApplicationBankService', CashApplicationBankService);

    CashApplicationBankService.$inject = ['$resource', 'InvoicedConfig'];

    function CashApplicationBankService($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/plaid_links/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                delete: {
                    method: 'DELETE',
                },
                link: {
                    method: 'POST',
                    isArray: true,
                },
                transactions: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/plaid_links/:id/transactions',
                    isArray: true,
                },
            },
        );
    }
})();
