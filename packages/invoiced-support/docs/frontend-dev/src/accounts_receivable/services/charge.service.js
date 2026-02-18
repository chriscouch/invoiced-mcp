(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('Charge', Charge);

    Charge.$inject = ['$resource', 'InvoicedConfig'];

    function Charge($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/charges/:id',
            {
                id: '@id',
            },
            {
                create: {
                    method: 'POST',
                },
                refund: {
                    url: InvoicedConfig.apiBaseUrl + '/charges/:id/refunds',
                    method: 'POST',
                },
            },
        );
    }
})();
