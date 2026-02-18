(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('InvoiceCapture', InvoiceCapture);

    InvoiceCapture.$inject = ['$resource', 'InvoicedConfig'];

    function InvoiceCapture($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/invoice/capture/:id',
            {
                id: '@id',
            },
            {
                import: {
                    method: 'POST',
                },
                completed: {
                    url: InvoicedConfig.apiBaseUrl + '/invoice/capture/:id/completed',
                    method: 'GET',
                },
            },
        );
    }
})();
