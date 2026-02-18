(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('PaymentLink', PaymentLink);

    PaymentLink.$inject = ['$resource', 'InvoicedConfig'];

    function PaymentLink($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/payment_links/:id',
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
                completedSessions: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/payment_links/:id/sessions',
                    isArray: true,
                },
            },
        );
    }
})();
