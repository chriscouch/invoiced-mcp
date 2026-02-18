(function () {
    'use strict';

    angular.module('app.integrations').factory('Flywire', Flywire);

    Flywire.$inject = ['$resource', 'InvoicedConfig'];

    function Flywire($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/flywire/:id',
            {
                id: '@id',
            },
            {
                findAllPayments: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/flywire/payments',
                    isArray: true,
                },
                findAllRefunds: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/flywire/refunds',
                    isArray: true,
                },
                findAllDisbursements: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/flywire/disbursements',
                    isArray: true,
                },
                getDisbursement: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/flywire/disbursements/:id',
                },
                getDisbursementPayouts: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/flywire/disbursements/:id/payouts',
                    isArray: true,
                },
                getPaymentPayouts: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/flywire/payments/:id/payouts',
                    isArray: true,
                },
            },
        );
    }
})();
