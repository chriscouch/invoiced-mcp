(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('CustomerPaymentBatch', CustomerPaymentBatch);

    CustomerPaymentBatch.$inject = ['$resource', '$q', 'Core', 'InvoicedConfig'];

    function CustomerPaymentBatch($resource, $q, Core, InvoicedConfig) {
        let customerPaymentBatch = $resource(
            InvoicedConfig.apiBaseUrl + '/customer_payment_batches/:id',
            {
                id: '@id',
            },
            {
                chargesToProcess: {
                    url: InvoicedConfig.apiBaseUrl + '/customer_payment_batches/charges_to_process',
                    method: 'GET',
                    isArray: true,
                },
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
                getItems: {
                    url: InvoicedConfig.apiBaseUrl + '/customer_payment_batches/:id/items',
                    method: 'GET',
                    isArray: true,
                },
                complete: {
                    url: InvoicedConfig.apiBaseUrl + '/customer_payment_batches/:id/complete',
                    method: 'POST',
                },
                paymentFile: {
                    method: 'POST',
                    responseType: 'blob',
                    headers: {
                        accept: 'text/plain',
                    },
                    url: InvoicedConfig.apiBaseUrl + '/customer_payment_batches/:id/payment_file',
                    transformResponse: function (data, headers) {
                        let header = headers();
                        if (header['content-type'] === 'application/json') {
                            return data;
                        }

                        return { blob: data };
                    },
                },
            },
        );

        customerPaymentBatch.downloadPaymentFile = function (batchPayment, params, success, error) {
            customerPaymentBatch.paymentFile(
                {
                    id: batchPayment.id,
                },
                params,
                function (result, headers) {
                    // Determine the filename
                    let filename = Core.getDispositionFilename(headers) || 'Payment File.txt';

                    // Download the print file
                    Core.createAndDownloadBlobFile(result.blob, filename);

                    success();
                },
                function (response) {
                    Core.decodeBlobError(response, error);
                },
            );
        };

        return customerPaymentBatch;
    }
})();
