(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('BankAccount', BankAccount);

    BankAccount.$inject = ['$resource', '$q', 'InvoicedConfig'];

    function BankAccount($resource, $q, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/vendor_bank_accounts/:id',
            {
                id: '@id',
            },
            {
                find: {
                    method: 'GET',
                },
                findAll: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        sort: 'name ASC',
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
                deletePlaid: {
                    url: InvoicedConfig.apiBaseUrl + '/vendor_bank_accounts/:id/plaid',
                    method: 'DELETE',
                },
                link: {
                    url: InvoicedConfig.apiBaseUrl + '/vendor_bank_accounts/plaid_links',
                    method: 'POST',
                    isArray: true,
                },
            },
        );
    }
})();
