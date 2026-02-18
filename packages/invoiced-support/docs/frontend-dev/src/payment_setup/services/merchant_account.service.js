(function () {
    'use strict';

    angular.module('app.payment_setup').factory('MerchantAccount', MerchantAccountService);

    MerchantAccountService.$inject = ['$resource', 'InvoicedConfig'];

    function MerchantAccountService($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/merchant_accounts/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                retrieve: {
                    method: 'GET',
                },
                create: {
                    method: 'POST',
                },
                test: {
                    params: {
                        id: 'test',
                    },
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
