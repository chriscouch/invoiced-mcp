(function () {
    'use strict';

    angular.module('app.network').factory('Network', Network);

    Network.$inject = ['$resource', '$q', 'InvoicedConfig'];

    function Network($resource, $q, InvoicedConfig) {
        let Network = $resource(
            InvoicedConfig.apiBaseUrl + '/network',
            {},
            {
                customers: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/network/customers',
                    isArray: true,
                },
                findCustomer: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/network/customers/:id',
                    params: {
                        id: '@id',
                    },
                },
                vendors: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/network/vendors',
                    isArray: true,
                },
                findVendor: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/network/vendors/:id',
                    params: {
                        id: '@id',
                    },
                },
                invitations: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/network/invitations',
                    isArray: true,
                },
                sendInvite: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/network/invitations',
                },
                deleteConnection: {
                    method: 'DELETE',
                    url: InvoicedConfig.apiBaseUrl + '/network/:type/:id',
                },
            },
        );

        Network.allCustomers = function (success, error) {
            return $q(function (resolve, reject) {
                let perPage = 100;
                Network.customers(
                    {
                        page: 1,
                        per_page: perPage,
                    },
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
                            Network.customers(
                                {
                                    page: page,
                                    per_page: perPage,
                                    paginate: 'none',
                                },
                                resolve2,
                                reject2,
                            );
                        }
                    },
                    reject,
                );
            }).then(success, error);
        };

        Network.allVendors = function (success, error) {
            return $q(function (resolve, reject) {
                let perPage = 100;
                Network.vendors(
                    {
                        page: 1,
                        per_page: perPage,
                    },
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
                            Network.vendors(
                                {
                                    page: page,
                                    per_page: perPage,
                                    paginate: 'none',
                                },
                                resolve2,
                                reject2,
                            );
                        }
                    },
                    reject,
                );
            }).then(success, error);
        };

        return Network;
    }
})();
