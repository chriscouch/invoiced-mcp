(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('VendorPaymentBatch', VendorPaymentBatch);

    VendorPaymentBatch.$inject = ['$resource', '$q', 'Core', 'InvoicedConfig'];

    function VendorPaymentBatch($resource, $q, Core, InvoicedConfig) {
        let vendorPaymentBatch = $resource(
            InvoicedConfig.apiBaseUrl + '/vendor_payment_batches/:id',
            {
                id: '@id',
            },
            {
                billsToPay: {
                    url: InvoicedConfig.apiBaseUrl + '/vendor_payment_batches/bills_to_pay',
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
                    url: InvoicedConfig.apiBaseUrl + '/vendor_payment_batches/:id/items',
                    method: 'GET',
                    isArray: true,
                },
                pay: {
                    url: InvoicedConfig.apiBaseUrl + '/vendor_payment_batches/:id/pay',
                    method: 'POST',
                },
                print: {
                    method: 'GET',
                    responseType: 'blob',
                    headers: {
                        accept: 'application/pdf',
                    },
                    url: InvoicedConfig.apiBaseUrl + '/vendor_payment_batches/:id/pdf',
                    transformResponse: function (data, headers) {
                        let header = headers();
                        if (header['content-type'] === 'application/json') {
                            return data;
                        }

                        return { blob: data };
                    },
                },
                paymentFile: {
                    method: 'POST',
                    responseType: 'blob',
                    headers: {
                        accept: 'text/plain',
                    },
                    url: InvoicedConfig.apiBaseUrl + '/vendor_payment_batches/:id/payment_file',
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

        vendorPaymentBatch.printCheck = function (batchPayment, success, error) {
            vendorPaymentBatch.print(
                {
                    id: batchPayment.id,
                },
                function (result, headers) {
                    // Determine the filename
                    let filename = Core.getDispositionFilename(headers) || 'Print Check.pdf';

                    // Download the print file
                    Core.createAndDownloadBlobFile(result.blob, filename);

                    success();
                },
                function (response) {
                    Core.decodeBlobError(response, error);
                },
            );
        };

        vendorPaymentBatch.downloadPaymentFile = function (batchPayment, params, success, error) {
            vendorPaymentBatch.paymentFile(
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

        return vendorPaymentBatch;
    }
})();
