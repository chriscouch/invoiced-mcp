/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('Transaction', Transaction);

    Transaction.$inject = ['$resource', '$http', 'InvoicedConfig'];

    function Transaction($resource, $http, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/transactions/:id/:item',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    params: {
                        exclude: 'metadata',
                        include: 'customerName,document_number',
                    },
                    isArray: true,
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let transactions = response;

                        angular.forEach(transactions, function (transaction) {
                            transaction.date = moment.unix(transaction.date).toDate();
                        });

                        return transactions;
                    }),
                },
                find: {
                    method: 'GET',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let transaction = response;

                        transaction.date = moment.unix(transaction.date).toDate();

                        return transaction;
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

                        let transaction = response;

                        transaction.date = moment.unix(transaction.date).toDate();

                        return transaction;
                    }),
                },
                delete: {
                    method: 'DELETE',
                },
                refund: {
                    method: 'POST',
                    params: {
                        item: 'refunds',
                    },
                },
                accountingSyncStatus: {
                    method: 'GET',
                    params: {
                        item: 'accounting_sync_status',
                    },
                },
            },
        );
    }
})();
