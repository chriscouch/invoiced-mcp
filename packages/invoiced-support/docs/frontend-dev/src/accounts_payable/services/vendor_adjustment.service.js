(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('VendorAdjustment', VendorAdjustment);

    VendorAdjustment.$inject = ['$resource', 'InvoicedConfig'];

    function VendorAdjustment($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/vendor_adjustments/:id',
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
                delete: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
