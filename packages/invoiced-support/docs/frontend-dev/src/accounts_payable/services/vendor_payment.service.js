(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('VendorPayment', VendorPayment);

    VendorPayment.$inject = ['$resource', 'Core', 'InvoicedConfig'];

    function VendorPayment($resource, Core, InvoicedConfig) {
        let vendorPayment = $resource(
            InvoicedConfig.apiBaseUrl + '/vendor_payments/:id',
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
                attachments: {
                    url: InvoicedConfig.apiBaseUrl + '/vendor_payments/:id/attachments',
                    method: 'GET',
                    isArray: true,
                },
                attach: {
                    url: InvoicedConfig.apiBaseUrl + '/vendor_payments/:id/attachment',
                    method: 'POST',
                },
                print: {
                    method: 'GET',
                    responseType: 'blob',
                    headers: {
                        accept: 'application/pdf',
                    },
                    url: InvoicedConfig.apiBaseUrl + '/vendor_payments/:id/pdf',
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

        vendorPayment.printCheck = function (payment, success, error) {
            vendorPayment.print(
                {
                    id: payment.id,
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

        return vendorPayment;
    }
})();
