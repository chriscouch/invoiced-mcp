(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('Bill', Bill);

    Bill.$inject = ['$resource', '$q', 'InvoicedConfig'];

    function Bill($resource, $q, InvoicedConfig) {
        let bill = $resource(
            InvoicedConfig.apiBaseUrl + '/bills/:id',
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
                balance: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/bills/:id/balance',
                },
                approvals: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/bills/:id/approval',
                    isArray: true,
                },
                rejections: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/bills/:id/rejection',
                    isArray: true,
                },
                approve: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/bills/:id/approval',
                },
                reject: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/bills/:id/rejection',
                },
                pay: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/pay',
                },
                attachments: {
                    url: InvoicedConfig.apiBaseUrl + '/bills/:id/attachments',
                    method: 'GET',
                    isArray: true,
                },
                attach: {
                    url: InvoicedConfig.apiBaseUrl + '/bills/:id/attachment',
                    method: 'POST',
                },
            },
        );

        bill.all = function (parameters, success, error) {
            //promise is needed to handle multiply concurrent requests
            let call = $q(function (resolve, reject) {
                let perPage = 100;
                let params = angular.extend(
                    {
                        page: 1,
                        per_page: perPage,
                    },
                    parameters,
                );
                bill.findAll(
                    params,
                    function (data, headers) {
                        let total = headers('X-Total-Count');
                        let page = 2;
                        if (total > data.length) {
                            let promises = [];
                            let pages = Math.ceil(total / perPage);
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
                            let params2 = angular.extend(params, {
                                page: page,
                                paginate: 'none',
                            });
                            bill.findAll(params2, resolve2, reject2);
                        }
                    },
                    reject,
                );
            });
            call.then(success, error);
        };

        return bill;
    }
})();
