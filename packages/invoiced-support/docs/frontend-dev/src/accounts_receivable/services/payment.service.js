/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('Payment', PaymentService);

    PaymentService.$inject = ['$resource', '$http', 'InvoicedConfig', 'selectedCompany'];

    function PaymentService($resource, $http, InvoicedConfig, selectedCompany) {
        let paymentsCache = {};

        let Payment = $resource(
            InvoicedConfig.apiBaseUrl + '/payments/:id/:item',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    params: {
                        include: 'customerName,bank_account_name',
                        per_page: 100,
                    },
                    isArray: true,
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let payments = response;

                        angular.forEach(payments, function (payment) {
                            payment.date = moment.unix(payment.date).toDate();
                        });

                        return payments;
                    }),
                },
                find: {
                    method: 'GET',
                    params: {
                        include: 'bank_account_name',
                    },
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let payment = response;

                        payment.date = moment.unix(payment.date).toDate();

                        return payment;
                    }),
                },
                create: {
                    method: 'POST',
                },
                edit: {
                    method: 'PATCH',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let payment = response;

                        payment.date = moment.unix(payment.date).toDate();

                        return payment;
                    }),
                },
                delete: {
                    method: 'DELETE',
                },
                email: {
                    method: 'POST',
                    params: {
                        item: 'emails',
                    },
                    isArray: true,
                },
                attachments: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        item: 'attachments',
                    },
                },
                matches: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        item: 'matches',
                    },
                },
                rejectMatch: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/cash_application/matches/:id/unsuccessful',
                    isArray: true,
                },
                accountingSyncStatus: {
                    method: 'GET',
                    params: {
                        item: 'accounting_sync_status',
                    },
                },
            },
        );

        Payment.all = function (params, success, error) {
            if (typeof paymentsCache[selectedCompany.id] !== 'undefined') {
                success(paymentsCache[selectedCompany.id]);
                return;
            }

            paymentsCache[selectedCompany.id] = [];
            loadPage(1, params, success, error);
        };

        Payment.clearCache = clearCache;

        return Payment;

        function loadPage(page, params, success, error) {
            params.page = page;
            Payment.findAll(
                params,
                function (plans, headers) {
                    paymentsCache[selectedCompany.id] = paymentsCache[selectedCompany.id].concat(plans);

                    // is there another page?
                    let hasMore = headers('X-Total-Count') > paymentsCache[selectedCompany.id].length;
                    if (hasMore) {
                        loadPage(page + 1, params, success, error);
                    } else {
                        success(paymentsCache[selectedCompany.id]);
                    }
                },
                error,
            );
        }

        function clearCache(response) {
            if (typeof paymentsCache[selectedCompany.id] !== 'undefined') {
                delete paymentsCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
