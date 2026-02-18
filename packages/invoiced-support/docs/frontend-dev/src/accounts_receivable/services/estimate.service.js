/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('Estimate', Estimate);

    Estimate.$inject = ['$resource', '$http', 'selectedCompany', 'InvoicedConfig'];

    function Estimate($resource, $http, selectedCompany, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/estimates/:id/:item',
            {
                id: '@id',
                item: '@item',
                type: '@type',
            },
            {
                findAll: {
                    method: 'GET',
                    params: {
                        exclude: 'items,discounts,taxes,shipping,ship_to,approval,metadata',
                        include: 'customerName',
                    },
                    isArray: true,
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let estimates = response;

                        angular.forEach(estimates, function (estimate) {
                            estimate.date = moment.unix(estimate.date).toDate();

                            if (estimate.expiration_date > 0) {
                                estimate.expiration_date = moment.unix(estimate.expiration_date).toDate();
                            } else {
                                estimate.expiration_date = null;
                            }
                        });

                        return estimates;
                    }),
                },
                find: {
                    method: 'GET',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let estimate = response;

                        // parse dates
                        estimate.date = moment.unix(estimate.date).toDate();
                        parseDiscountExpirationDates(estimate.discounts);

                        if (estimate.expiration_date > 0) {
                            estimate.expiration_date = moment.unix(estimate.expiration_date).toDate();
                        } else {
                            estimate.expiration_date = null;
                        }

                        // parse items
                        try {
                            estimate.items = angular.fromJson(estimate.items);

                            angular.forEach(estimate.items, function (item) {
                                parseDiscountExpirationDates(item.discounts);
                            });
                        } catch (e) {
                            window.console.log(e);
                        }

                        return estimate;
                    }),
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
                email: {
                    method: 'POST',
                    params: {
                        item: 'emails',
                    },
                    isArray: true,
                },
                sendInvoiced: {
                    method: 'POST',
                    params: {
                        item: 'send',
                    },
                },
                makeInvoiceFromEstimate: {
                    method: 'POST',
                    params: {
                        item: 'invoice',
                    },
                },
                lineItems: {
                    method: 'GET',
                    params: {
                        item: 'line_items',
                    },
                    isArray: true,
                },
                attachments: {
                    method: 'GET',
                    params: {
                        item: 'attachments',
                    },
                    isArray: true,
                },
                void: {
                    method: 'POST',
                    params: {
                        item: 'void',
                    },
                },
            },
        );

        function parseDiscountExpirationDates(discounts) {
            // discount expiration dates
            angular.forEach(discounts, function (discount) {
                if (discount.expires) {
                    discount.expires = moment.unix(discount.expires).toDate();
                }
            });
        }
    }
})();
