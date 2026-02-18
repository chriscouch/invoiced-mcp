/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('Invoice', InvoiceService);

    InvoiceService.$inject = ['$resource', '$http', '$q', 'selectedCompany', 'InvoicedConfig'];

    function InvoiceService($resource, $http, $q, selectedCompany, InvoicedConfig) {
        let Invoice = $resource(
            InvoicedConfig.apiBaseUrl + '/invoices/:id/:item',
            {
                id: '@id',
                item: '@item',
            },
            {
                findAll: {
                    method: 'GET',
                    params: {
                        exclude: 'items,discounts,taxes,shipping,ship_to,payment_source,metadata',
                        include: 'customerName',
                    },
                    isArray: true,
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let invoices = response;

                        angular.forEach(invoices, function (invoice) {
                            Invoice.parseFromResponse(invoice);
                        });

                        return invoices;
                    }),
                },
                find: {
                    method: 'GET',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let invoice = response;

                        Invoice.parseFromResponseWithItems(invoice);

                        return invoice;
                    }),
                },
                create: {
                    method: 'POST',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let invoice = response;

                        Invoice.parseFromResponseWithItems(invoice);

                        return invoice;
                    }),
                },
                edit: {
                    method: 'PATCH',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let invoice = response;

                        Invoice.parseFromResponseWithItems(invoice);

                        return invoice;
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
                sendInvoiced: {
                    method: 'POST',
                    params: {
                        item: 'send',
                    },
                },
                sendLetter: {
                    method: 'POST',
                    params: {
                        item: 'letters',
                    },
                },
                sendTextMessage: {
                    method: 'POST',
                    params: {
                        item: 'text_messages',
                    },
                    isArray: true,
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
                distributions: {
                    method: 'GET',
                    params: {
                        item: 'distributions',
                    },
                    isArray: true,
                },
                notes: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/invoices/:id/notes',
                    isArray: true,
                },
                paymentPlan: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/invoices/:id/payment_plan',
                },
                setPaymentPlan: {
                    method: 'PUT',
                    url: InvoicedConfig.apiBaseUrl + '/invoices/:id/payment_plan',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let invoice = response;

                        Invoice.parseFromResponseWithItems(invoice);

                        return invoice;
                    }),
                },
                cancelPaymentPlan: {
                    method: 'DELETE',
                    url: InvoicedConfig.apiBaseUrl + '/invoices/:id/payment_plan',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let invoice = response;

                        Invoice.parseFromResponseWithItems(invoice);

                        return invoice;
                    }),
                },
                pay: {
                    method: 'POST',
                    params: {
                        item: 'pay',
                    },
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let invoice = response;

                        Invoice.parseFromResponseWithItems(invoice);

                        return invoice;
                    }),
                },
                void: {
                    method: 'POST',
                    params: {
                        item: 'void',
                    },
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let invoice = response;

                        Invoice.parseFromResponseWithItems(invoice);

                        return invoice;
                    }),
                },
                bad_debt: {
                    method: 'POST',
                    params: {
                        item: 'bad_debt',
                    },
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let invoice = response;

                        Invoice.parseFromResponseWithItems(invoice);

                        return invoice;
                    }),
                },
                accountingSyncStatus: {
                    method: 'GET',
                    params: {
                        item: 'accounting_sync_status',
                    },
                },
                getDelivery: {
                    method: 'GET',
                    params: {
                        item: 'delivery',
                    },
                },
                setDelivery: {
                    method: 'PUT',
                    params: {
                        item: 'delivery',
                    },
                },
                getChaseState: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/invoices/:id/delivery/state',
                    isArray: true,
                },
            },
        );

        Invoice.parseFromResponse = function (invoice) {
            // parse dates
            invoice.date = moment.unix(invoice.date).toDate();

            if (invoice.due_date > 0) {
                invoice.due_date = moment.unix(invoice.due_date).toDate();
            } else {
                invoice.due_date = null;
            }

            if (invoice.discounts) {
                parseDiscountExpirationDates(invoice.discounts);
            }

            // expected payment date
            if (
                typeof invoice.expected_payment_date !== 'undefined' &&
                invoice.expected_payment_date &&
                invoice.expected_payment_date.date
            ) {
                invoice.expected_payment_date.date = moment.unix(invoice.expected_payment_date.date).toDate();
            }

            // amount paid
            invoice.amount_paid = invoice.total - invoice.balance;
        };

        Invoice.parseFromResponseWithItems = function (invoice) {
            Invoice.parseFromResponse(invoice);

            // parse items
            try {
                invoice.items = angular.fromJson(invoice.items);

                angular.forEach(invoice.items, function (item) {
                    parseDiscountExpirationDates(item.discounts);
                });
            } catch (e) {
                window.console.log(e);
            }
        };

        Invoice.all = function (parameters, success, error) {
            Invoice.findAll(
                parameters,
                function (invoices, headers) {
                    let pages = Math.ceil(headers('X-Total-Count') / 100);
                    let promises = [];
                    for (let page = 2; page <= pages; ++page) {
                        let params = angular.copy(parameters);
                        params.page = page;
                        params.paginate = 'none';
                        promises.push(createPagePromise(params));
                    }
                    $q.all(promises).then(function resolveValues(values) {
                        for (let i = 0; i < values.length; ++i) {
                            invoices = invoices.concat(values[i]);
                        }
                        success(invoices);
                    });
                },
                error,
            );
        };

        return Invoice;

        function createPagePromise(params) {
            return $q(function (resolve) {
                Invoice.findAll(params, function (invoices) {
                    resolve(invoices);
                });
            });
        }

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
