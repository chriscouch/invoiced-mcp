(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('RemittanceAdvice', RemittanceAdviceService);

    RemittanceAdviceService.$inject = ['$resource', '$http', 'InvoicedConfig'];

    function RemittanceAdviceService($resource, $http, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/remittance_advice/:id/:item',
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
                postPayment: {
                    method: 'POST',
                    params: {
                        item: 'payment',
                    },
                },
                attachments: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        item: 'attachments',
                    },
                },
                upload: {
                    method: 'POST',
                    params: {
                        item: 'upload',
                    },
                },
                resolveLine: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/remittance_advice/:id/lines/:lineId/resolve',
                    params: {
                        id: '@id',
                        lineId: '@lineId',
                    },
                },
            },
        );
    }
})();
