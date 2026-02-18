(function () {
    'use strict';

    angular.module('app.network').factory('NetworkDocument', NetworkDocument);

    NetworkDocument.$inject = ['$resource', '$http', 'InvoicedConfig'];

    function NetworkDocument($resource, $http, InvoicedConfig) {
        let NetworkDocument = $resource(
            InvoicedConfig.apiBaseUrl + '/network',
            {},
            {
                findAll: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/network/documents',
                    isArray: true,
                },
                find: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/network/documents/:id',
                },
                setStatus: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/network/documents/:id/current_status',
                },
            },
        );

        NetworkDocument.download = function (id, pdf, success, error) {
            $http
                .get(InvoicedConfig.apiBaseUrl + '/network/documents/' + id, {
                    responseType: 'blob',
                    headers: {
                        Accept: pdf ? 'application/pdf,text/xml' : 'text/xml',
                    },
                })
                .then(
                    function successCallback(result) {
                        let disposition = result.headers('Content-Disposition') + '';
                        let match = disposition.match(/filename="(.+)"/);
                        success(result.data, match[1]);
                    },
                    function errorCallback(result) {
                        result.data
                            .text()
                            .then(function (value) {
                                error(JSON.parse(value));
                            })
                            .catch(function (error) {
                                error({ message: 'An unknown error occurred: ' + error });
                            });
                    },
                );
        };

        return NetworkDocument;
    }
})();
