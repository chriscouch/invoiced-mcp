(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('ApprovalWorkflow', ApprovalWorkflow);

    ApprovalWorkflow.$inject = ['$resource', '$q', 'InvoicedConfig'];

    function ApprovalWorkflow($resource, $q, InvoicedConfig) {
        let ApprovalWorkflow = $resource(
            InvoicedConfig.apiBaseUrl + '/approvals/workflows/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        sort: 'name ASC',
                    },
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
                unSetDefault: {
                    method: 'DELETE',
                    url: InvoicedConfig.apiBaseUrl + '/approvals/workflows/:id/default',
                },
                setDefault: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/approvals/workflows/:id/default',
                },
                disable: {
                    method: 'DELETE',
                    url: InvoicedConfig.apiBaseUrl + '/approvals/workflows/:id/enabled',
                },
                enable: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/approvals/workflows/:id/enabled',
                },
            },
        );

        ApprovalWorkflow.all = function (parameters, success, error) {
            //promise is needed to handle multiply concurrent requests
            $q(function (resolve, reject) {
                parameters.per_page = 100;
                let params = angular.copy(parameters);
                params.page = 1;
                ApprovalWorkflow.findAll(
                    params,
                    function (data, headers) {
                        let total = headers('X-Total-Count');
                        let page = 2;
                        if (total > data.length) {
                            let promises = [];
                            let pages = Math.ceil(total / parameters.per_page);
                            for (page = 2; page <= pages; ++page) {
                                promises.push($q(pagePromise));
                            }

                            $q.all(promises).then(function resolveValues(values) {
                                for (let i = 0; i < values.length; ++i) {
                                    data = data.concat(values[i]);
                                }
                                resolve(data);
                            });
                        } else {
                            resolve(data);
                        }
                        function pagePromise(resolve2, reject2) {
                            let params = angular.copy(parameters);
                            params.page = page;
                            params.paginate = 'none';
                            ApprovalWorkflow.findAll(params, resolve2, reject2);
                        }
                    },
                    reject,
                );
            }).then(success, error);
        };

        return ApprovalWorkflow;
    }
})();
