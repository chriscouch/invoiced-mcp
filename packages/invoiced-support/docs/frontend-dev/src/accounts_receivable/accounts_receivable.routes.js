(function () {
    'use strict';

    angular.module('app.accounts_receivable').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            // Customers
            .state('manage.customers', {
                abstract: true,
                url: '/customers',
                template: '<ui-view/>',
            })
            .state('manage.customers.browse', {
                url: '',
                templateUrl: 'accounts_receivable/views/customers/browse.html',
                controller: 'BrowseCustomersController',
                reloadOnSearch: false,
            })
            .state('manage.customer', {
                abstract: true,
                url: '/customers/:id',
                template: '<ui-view/>',
            })
            .state('manage.customer.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_receivable/views/customers/view.html',
                controller: 'ViewCustomerController',
            })
            .state('manage.customer.view.summary', {
                url: '',
                templateUrl: 'accounts_receivable/views/customers/summary.html',
                controller: [
                    '$scope',
                    function ($scope) {
                        $scope.loadPaymentSources($scope.modelId);
                        $scope.loadContacts($scope.modelId);
                        $scope.loadUpcomingInvoice($scope.modelId);
                        $scope.loadSubscriptions($scope.modelId);
                        $scope.loadEmailThreads($scope.modelId);
                        $scope.loadChildCustomers($scope.modelId);
                    },
                ],
            })
            .state('manage.customer.view.collections', {
                url: '/collections',
                templateUrl: 'accounts_receivable/views/customers/collections.html',
                controller: [
                    '$scope',
                    function ($scope) {
                        $scope.loadCollectionActivity($scope.modelId);
                        $scope.loadCollectionNotes($scope.modelId);
                        $scope.loadTasks($scope.modelId);
                    },
                ],
            })
            .state('manage.customer.view.report', {
                url: '/report',
                templateUrl: 'accounts_receivable/views/customers/report.html',
                controller: [
                    '$scope',
                    function ($scope) {
                        $scope.loadDashboard($scope.modelId, $scope.currency);

                        $scope.$watch(
                            'currency',
                            function (currency) {
                                $scope.loadDashboard($scope.modelId, currency);
                            },
                            true,
                        );
                    },
                ],
            })
            .state('manage.customer.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            })

            // Invoices
            .state('manage.invoices', {
                abstract: true,
                url: '/invoices',
                template: '<ui-view/>',
            })
            .state('manage.invoices.browse', {
                url: '',
                templateUrl: 'accounts_receivable/views/invoices/browse.html',
                controller: 'BrowseInvoicesController',
                reloadOnSearch: false,
            })
            .state('manage.invoices.new', {
                url: '/new',
                templateUrl: 'accounts_receivable/views/invoices/edit.html',
                controller: 'EditInvoiceController',
                resolve: {
                    allowed: allowed('invoices.create'),
                },
            })
            .state('manage.invoices.newWithCustomer', {
                url: '/new/:customer',
                templateUrl: 'accounts_receivable/views/invoices/edit.html',
                controller: 'EditInvoiceController',
                resolve: {
                    allowed: allowed('invoices.create'),
                },
            })
            .state('manage.invoice', {
                abstract: true,
                url: '/invoices/:id',
                template: '<ui-view/>',
            })
            .state('manage.invoice.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_receivable/views/invoices/view.html',
                controller: 'ViewInvoiceController',
            })
            .state('manage.invoice.view.summary', {
                url: '',
                templateUrl: 'accounts_receivable/views/invoices/summary.html',
            })
            .state('manage.invoice.view.messages', {
                url: '/conversation',
                templateUrl: 'inboxes/views/document-messages.html',
                controller: 'DocumentMessagesController',
                resolve: {
                    type: function () {
                        return 'invoice';
                    },
                },
            })
            .state('manage.invoice.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            })
            .state('manage.invoice.edit', {
                url: '/edit',
                templateUrl: 'accounts_receivable/views/invoices/edit.html',
                controller: 'EditInvoiceController',
                resolve: {
                    allowed: allowed('invoices.edit'),
                },
            })
            .state('manage.invoice.duplicate', {
                url: '/duplicate',
                templateUrl: 'accounts_receivable/views/invoices/edit.html',
                controller: 'EditInvoiceController',
                resolve: {
                    allowed: allowed('invoices.create'),
                },
            })

            // Credit Notes
            .state('manage.credit_notes', {
                abstract: true,
                url: '/credit_notes',
                template: '<ui-view/>',
            })
            .state('manage.credit_notes.browse', {
                url: '',
                templateUrl: 'accounts_receivable/views/credit-notes/browse.html',
                controller: 'BrowseCreditNotesController',
                reloadOnSearch: false,
            })
            .state('manage.credit_notes.new', {
                url: '/new',
                templateUrl: 'accounts_receivable/views/credit-notes/edit.html',
                controller: 'EditCreditNoteController',
                resolve: {
                    allowed: allowed('credit_notes.create'),
                },
            })
            .state('manage.credit_notes.newWithCustomer', {
                url: '/new/customer/:customer',
                templateUrl: 'accounts_receivable/views/credit-notes/edit.html',
                controller: 'EditCreditNoteController',
                resolve: {
                    allowed: allowed('credit_notes.create'),
                },
            })
            .state('manage.credit_notes.newWithInvoice', {
                url: '/new/invoice/:invoice',
                templateUrl: 'accounts_receivable/views/credit-notes/edit.html',
                controller: 'EditCreditNoteController',
                resolve: {
                    allowed: allowed('credit_notes.create'),
                },
            })
            .state('manage.credit_note', {
                abstract: true,
                url: '/credit_notes/:id',
                template: '<ui-view/>',
            })
            .state('manage.credit_note.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_receivable/views/credit-notes/view.html',
                controller: 'ViewCreditNoteController',
            })
            .state('manage.credit_note.view.summary', {
                url: '',
                templateUrl: 'accounts_receivable/views/credit-notes/summary.html',
            })
            .state('manage.credit_note.view.messages', {
                url: '/conversation',
                templateUrl: 'inboxes/views/document-messages.html',
                controller: 'DocumentMessagesController',
                resolve: {
                    type: function () {
                        return 'credit_note';
                    },
                },
            })
            .state('manage.credit_note.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            })
            .state('manage.credit_note.edit', {
                url: '/edit',
                templateUrl: 'accounts_receivable/views/credit-notes/edit.html',
                controller: 'EditCreditNoteController',
                resolve: {
                    allowed: allowed('credit_notes.edit'),
                },
            })
            .state('manage.credit_note.duplicate', {
                url: '/duplicate',
                templateUrl: 'accounts_receivable/views/credit-notes/edit.html',
                controller: 'EditCreditNoteController',
                resolve: {
                    allowed: allowed('credit_notes.create'),
                },
            })

            // Estimates
            .state('manage.estimates', {
                abstract: true,
                url: '/estimates',
                template: '<ui-view/>',
            })
            .state('manage.estimates.browse', {
                url: '',
                templateUrl: 'accounts_receivable/views/estimates/browse.html',
                controller: 'BrowseEstimatesController',
                reloadOnSearch: false,
            })
            .state('manage.estimates.new', {
                url: '/new',
                templateUrl: 'accounts_receivable/views/estimates/edit.html',
                controller: 'EditEstimateController',
                resolve: {
                    allowed: allowed('estimates.create'),
                },
            })
            .state('manage.estimates.newWithCustomer', {
                url: '/new/:customer',
                templateUrl: 'accounts_receivable/views/estimates/edit.html',
                controller: 'EditEstimateController',
                resolve: {
                    allowed: allowed('estimates.create'),
                },
            })
            .state('manage.estimate', {
                abstract: true,
                url: '/estimates/:id',
                template: '<ui-view/>',
            })
            .state('manage.estimate.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_receivable/views/estimates/view.html',
                controller: 'ViewEstimateController',
            })
            .state('manage.estimate.view.summary', {
                url: '',
                templateUrl: 'accounts_receivable/views/estimates/summary.html',
                controller: [
                    '$scope',
                    function ($scope) {
                        $scope.loadAttachments($scope.modelId);
                    },
                ],
            })
            .state('manage.estimate.view.messages', {
                url: '/conversation',
                templateUrl: 'inboxes/views/document-messages.html',
                controller: 'DocumentMessagesController',
                resolve: {
                    type: function () {
                        return 'estimate';
                    },
                },
            })
            .state('manage.estimate.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            })
            .state('manage.estimate.edit', {
                url: '/edit',
                templateUrl: 'accounts_receivable/views/estimates/edit.html',
                controller: 'EditEstimateController',
                resolve: {
                    allowed: allowed('estimates.edit'),
                },
            })
            .state('manage.estimate.duplicate', {
                url: '/duplicate',
                templateUrl: 'accounts_receivable/views/estimates/edit.html',
                controller: 'EditEstimateController',
                resolve: {
                    allowed: allowed('estimates.create'),
                },
            })

            // Payment Links
            .state('manage.payment_links', {
                abstract: true,
                url: '/payment_links',
                template: '<ui-view/>',
            })
            .state('manage.payment_links.browse', {
                url: '',
                templateUrl: 'components/views/table-view.html',
                controller: 'BrowsePaymentLinksController',
                reloadOnSearch: false,
            })
            .state('manage.payment_links.new', {
                url: '/new',
                templateUrl: 'accounts_receivable/views/payment-links/edit.html',
                controller: 'EditPaymentLinkController',
                resolve: {
                    allowed: allowed('catalog.edit'),
                },
            })
            .state('manage.payment_links.newWithCustomer', {
                url: '/new/:customer',
                templateUrl: 'accounts_receivable/views/payment-links/edit.html',
                controller: 'EditPaymentLinkController',
                resolve: {
                    allowed: allowed('catalog.edit'),
                },
            })
            .state('manage.payment_link', {
                abstract: true,
                url: '/payment_links/:id',
                template: '<ui-view/>',
            })
            .state('manage.payment_link.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_receivable/views/payment-links/view.html',
                controller: 'ViewPaymentLinkController',
            })
            .state('manage.payment_link.view.summary', {
                url: '',
                templateUrl: 'accounts_receivable/views/payment-links/summary.html',
            })
            .state('manage.payment_link.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            })
            .state('manage.payment_link.edit', {
                url: '/edit',
                templateUrl: 'accounts_receivable/views/payment-links/edit.html',
                controller: 'EditPaymentLinkController',
                resolve: {
                    allowed: allowed('catalog.edit'),
                },
            })
            .state('manage.payment_link.duplicate', {
                url: '/duplicate',
                templateUrl: 'accounts_receivable/views/payment-links/edit.html',
                controller: 'EditPaymentLinkController',
                resolve: {
                    allowed: allowed('catalog.edit'),
                },
            })

            // Payments
            .state('manage.payments', {
                abstract: true,
                url: '/payments',
                template: '<ui-view/>',
            })
            .state('manage.payments.browse', {
                url: '',
                templateUrl: 'accounts_receivable/views/payments/browse.html',
                controller: 'BrowsePaymentsController',
                reloadOnSearch: false,
            })
            .state('manage.payment', {
                abstract: true,
                url: '/payments/:id',
                template: '<ui-view/>',
            })
            .state('manage.payment.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_receivable/views/payments/view.html',
                controller: 'ViewPaymentController',
            })
            .state('manage.payment.view.summary', {
                url: '',
                templateUrl: 'accounts_receivable/views/payments/summary.html',
            })
            .state('manage.payment.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            })

            // Payment Batches
            .state('manage.customer_payment_batches', {
                abstract: true,
                url: '/customer_payment_batches',
                template: '<ui-view/>',
            })
            .state('manage.customer_payment_batches.browse', {
                url: '',
                templateUrl: 'accounts_receivable/views/payment_batches/browse.html',
                controller: 'BrowseCustomerPaymentBatchesController',
                reloadOnSearch: false,
            })
            .state('manage.customer_payment_batches.new', {
                url: '/new',
                templateUrl: 'accounts_receivable/views/payment_batches/new.html',
                controller: 'NewCustomerPaymentBatchController',
                resolve: {
                    allowed: allowed('charges.create'),
                },
            })
            .state('manage.customer_payment_batches.summary', {
                url: '/:id',
                templateUrl: 'accounts_receivable/views/payment_batches/summary.html',
                controller: 'ViewCustomerPaymentBatchController',
                reloadOnSearch: false,
            })

            // Remittance Advice
            .state('manage.remittance_advices', {
                abstract: true,
                url: '/remittance_advice',
                template: '<ui-view/>',
            })
            .state('manage.remittance_advices.browse', {
                url: '',
                templateUrl: 'components/views/table-view.html',
                controller: 'BrowseRemittanceAdviceController',
                reloadOnSearch: false,
            })
            .state('manage.remittance_advice', {
                abstract: true,
                url: '/remittance_advice/:id',
                template: '<ui-view/>',
            })
            .state('manage.remittance_advice.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_receivable/views/remittance-advice/view.html',
                controller: 'ViewRemittanceAdviceController',
            })
            .state('manage.remittance_advice.view.summary', {
                url: '',
                templateUrl: 'accounts_receivable/views/remittance-advice/summary.html',
            })
            .state('manage.remittance_advice.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            })

            // Transactions
            .state('manage.transactions', {
                abstract: true,
                url: '/transactions',
                template: '<ui-view/>',
            })
            .state('manage.transactions.browse', {
                url: '',
                templateUrl: 'components/views/table-view.html',
                controller: 'BrowseTransactionsController',
                reloadOnSearch: false,
            })
            .state('manage.transaction', {
                abstract: true,
                url: '/transactions/:id',
                template: '<ui-view/>',
            })
            .state('manage.transaction.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_receivable/views/transactions/view.html',
                controller: 'ViewTransactionController',
            })
            .state('manage.transaction.view.summary', {
                url: '',
                templateUrl: 'accounts_receivable/views/transactions/summary.html',
            })
            .state('manage.transaction.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            });
    }

    function allowed(permission) {
        return [
            'userBootstrap',
            '$q',
            'Permission',
            function (userBootstrap, $q, Permission) {
                if (Permission.hasPermission(permission)) {
                    return true;
                }

                return $q.reject('Not Authorized');
            },
        ];
    }
})();
