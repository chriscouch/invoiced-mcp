/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('CreditNote', CreditNoteService);

    CreditNoteService.$inject = ['$resource', '$http', 'selectedCompany', 'InvoicedConfig'];

    function CreditNoteService($resource, $http, selectedCompany, InvoicedConfig) {
        let CreditNote = $resource(
            InvoicedConfig.apiBaseUrl + '/credit_notes/:id/:item',
            {
                id: '@id',
                item: '@item',
            },
            {
                findAll: {
                    method: 'GET',
                    params: {
                        exclude: 'items,discounts,taxes,shipping,metadata',
                        include: 'customerName',
                    },
                    isArray: true,
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let creditNotes = response;

                        angular.forEach(creditNotes, function (creditNote) {
                            CreditNote.parseFromResponse(creditNote);
                        });

                        return creditNotes;
                    }),
                },
                find: {
                    method: 'GET',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let creditNote = response;

                        CreditNote.parseFromResponseWithItems(creditNote);

                        return creditNote;
                    }),
                },
                create: {
                    method: 'POST',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let creditNote = response;

                        CreditNote.parseFromResponseWithItems(creditNote);

                        return creditNote;
                    }),
                },
                edit: {
                    method: 'PATCH',
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let creditNote = response;

                        CreditNote.parseFromResponseWithItems(creditNote);

                        return creditNote;
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
                accountingSyncStatus: {
                    method: 'GET',
                    params: {
                        item: 'accounting_sync_status',
                    },
                },
            },
        );

        CreditNote.parseFromResponse = function (creditNote) {
            // parse dates
            creditNote.date = moment.unix(creditNote.date).toDate();

            // amount paid
            creditNote.amount_paid = creditNote.total - creditNote.balance;
        };

        CreditNote.parseFromResponseWithItems = function (creditNote) {
            CreditNote.parseFromResponse(creditNote);

            // parse items
            try {
                creditNote.items = angular.fromJson(creditNote.items);
            } catch (e) {
                window.console.log(e);
            }
        };

        return CreditNote;
    }
})();
