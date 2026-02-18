(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('Vendor', Vendor);

    Vendor.$inject = ['$resource', 'InvoicedConfig'];

    function Vendor($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/vendors/:id',
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
                balance: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/vendors/:id/balance',
                },
                paymentMethods: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/vendors/:id/payment_methods',
                    isArray: true,
                },
            },
        );
    }
})();
