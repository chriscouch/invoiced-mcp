(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('Dispute', Dispute);

    Dispute.$inject = ['$resource', '$http', 'InvoicedConfig'];

    function Dispute($resource, $http, InvoicedConfig) {
        let Dispute = $resource(
            InvoicedConfig.apiBaseUrl + '/disputes/:id',
            {
                id: '@id',
            },
            {
                acceptDispute: {
                    method: 'DELETE',
                    params: {
                        item: 'dispute',
                    },
                },
                deleteDisputeFile: {
                    method: 'DELETE',
                    url: InvoicedConfig.apiBaseUrl + '/disputes/:id/files/:fileCode',
                },
                getReasons: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/disputes/:id/reasons',
                    isArray: true,
                },
            },
        );

        Dispute.uploadDisputeFile = function (id, parameters, success, error) {
            const fd = new FormData();
            fd.append('files[]', parameters.file);
            fd.append('codes[]', parameters.code);

            $http
                .post(InvoicedConfig.apiBaseUrl + '/disputes/' + id + '/files', fd, {
                    withCredentials: false,
                    headers: {
                        'Content-Type': undefined,
                    },
                    transformRequest: angular.identity,
                    params: {
                        fd,
                    },
                    responseType: 'arraybuffer',
                })
                .then(success)
                .catch(error);
        };

        Dispute.defendDispute = function (id, parameters, success, error) {
            const fd = new FormData();
            for (const i in parameters.files) {
                fd.append('files[]', parameters.files[i].uploaded);
                fd.append('codes[]', parameters.files[i].defenseDocumentTypeCode);
            }

            fd.append('reason_code', parameters.reason_code);

            $http
                .post(InvoicedConfig.apiBaseUrl + '/disputes/' + id, fd, {
                    withCredentials: false,
                    headers: {
                        'Content-Type': undefined,
                    },
                    transformRequest: angular.identity,
                    params: {
                        fd,
                    },
                    responseType: 'arraybuffer',
                })
                .then(success)
                .catch(error);
        };

        return Dispute;
    }
})();
