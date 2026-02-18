(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('InvoiceDistribution', InvoiceDistribution);

    InvoiceDistribution.$inject = ['$resource', 'InvoicedConfig'];

    function InvoiceDistribution($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/invoice_distributions/:id',
            {
                id: '@id',
            },
            {
                edit: {
                    method: 'PATCH',
                },
            },
        );
    }
})();
