(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('VendorCredit', VendorCredit);

    VendorCredit.$inject = ['$resource', 'InvoicedConfig'];

    function VendorCredit($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/vendor_credits/:id',
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
                balance: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/vendor_credits/:id/balance',
                },
                approvals: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/vendor_credits/:id/approval',
                    isArray: true,
                },
                rejections: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/vendor_credits/:id/rejection',
                    isArray: true,
                },
                approve: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/vendor_credits/:id/approval',
                },
                reject: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/vendor_credits/:id/rejection',
                },
                attachments: {
                    url: InvoicedConfig.apiBaseUrl + '/vendor_credits/:id/attachments',
                    method: 'GET',
                    isArray: true,
                },
                attach: {
                    url: InvoicedConfig.apiBaseUrl + '/vendor_credits/:id/attachment',
                    method: 'POST',
                },
            },
        );
    }
})();
