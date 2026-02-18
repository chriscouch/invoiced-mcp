(function () {
    'use strict';

    angular.module('app.accounts_payable').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            // Vendors
            .state('manage.vendors', {
                abstract: true,
                url: '/vendors',
                template: '<ui-view/>',
            })
            .state('manage.vendors.browse', {
                url: '',
                templateUrl: 'accounts_payable/views/vendors/browse.html',
                controller: 'BrowseVendorsController',
                reloadOnSearch: false,
            })
            .state('manage.vendor', {
                abstract: true,
                url: '/vendors/:id',
                template: '<ui-view/>',
            })
            .state('manage.vendor.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_payable/views/vendors/view.html',
                controller: 'ViewVendorController',
            })
            .state('manage.vendor.view.summary', {
                url: '',
                templateUrl: 'accounts_payable/views/vendors/summary.html',
            })
            .state('manage.vendor.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            })

            // Bills
            .state('manage.bills', {
                abstract: true,
                url: '/bills',
                template: '<ui-view/>',
            })
            .state('manage.bills.browse', {
                url: '',
                templateUrl: 'accounts_payable/views/bills/browse.html',
                controller: 'BrowseBillsController',
            })
            .state('manage.bills.new', {
                url: '/new/:vendor',
                templateUrl: 'accounts_payable/views/bills/edit.html',
                controller: 'EditBillController',
                resolve: {
                    allowed: allowed('bills.create'),
                },
            })
            .state('manage.bill', {
                abstract: true,
                url: '/bills/:id',
                template: '<ui-view/>',
            })
            .state('manage.bill.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_payable/views/bills/view.html',
                controller: 'ViewBillController',
            })
            .state('manage.bill.view.summary', {
                url: '',
                templateUrl: 'accounts_payable/views/bills/summary.html',
            })
            .state('manage.bill.view.messages', {
                url: '/conversation',
                templateUrl: 'inboxes/views/document-messages.html',
                controller: 'DocumentMessagesController',
                resolve: {
                    type: function () {
                        return 'bill';
                    },
                },
            })
            .state('manage.bill.edit', {
                url: '/edit',
                templateUrl: 'accounts_payable/views/bills/edit.html',
                controller: 'EditBillController',
            })
            .state('manage.bill.pay', {
                url: '/pay',
                templateUrl: 'accounts_payable/views/bills/pay.html',
                controller: 'PayBillController',
            })

            // Vendor Credits
            .state('manage.vendor_credits', {
                abstract: true,
                url: '/vendor_credits',
                template: '<ui-view/>',
            })
            .state('manage.vendor_credits.browse', {
                url: '',
                templateUrl: 'accounts_payable/views/vendor_credits/browse.html',
                controller: 'BrowseVendorCreditsController',
            })
            .state('manage.vendor_credits.new', {
                url: '/new/:vendor',
                templateUrl: 'accounts_payable/views/vendor_credits/edit.html',
                controller: 'EditVendorCreditController',
                resolve: {
                    allowed: allowed('bills.create'),
                },
            })
            .state('manage.vendor_credit', {
                abstract: true,
                url: '/vendor_credits/:id',
                template: '<ui-view/>',
            })
            .state('manage.vendor_credit.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_payable/views/vendor_credits/view.html',
                controller: 'ViewVendorCreditController',
            })
            .state('manage.vendor_credit.view.summary', {
                url: '',
                templateUrl: 'accounts_payable/views/vendor_credits/summary.html',
            })
            .state('manage.vendor_credit.view.messages', {
                url: '/conversation',
                templateUrl: 'inboxes/views/messages.html',
                controller: 'ViewMessagesController',
                resolve: {
                    type: function () {
                        return 'vendor_credit';
                    },
                },
            })
            .state('manage.vendor_credit.edit', {
                url: '/edit',
                templateUrl: 'accounts_payable/views/vendor_credits/edit.html',
                controller: 'EditVendorCreditController',
            })

            // Vendor Adjustments
            .state('manage.vendor_adjustment', {
                abstract: true,
                url: '/vendor_adjustments/:id',
                template: '<ui-view/>',
            })
            .state('manage.vendor_adjustment.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_payable/views/adjustments/view.html',
                controller: 'ViewVendorAdjustmentController',
            })
            .state('manage.vendor_adjustment.view.summary', {
                url: '',
                templateUrl: 'accounts_payable/views/adjustments/summary.html',
            })
            .state('manage.vendor_adjustment.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            })

            // Vendor Payments
            .state('manage.vendor_payments', {
                abstract: true,
                url: '/vendor_payments',
                template: '<ui-view/>',
            })
            .state('manage.vendor_payments.browse', {
                url: '',
                templateUrl: 'accounts_payable/views/payments/browse.html',
                controller: 'BrowseVendorPaymentsController',
                reloadOnSearch: false,
            })
            .state('manage.vendor_payment', {
                abstract: true,
                url: '/vendor_payments/:id',
                template: '<ui-view/>',
            })
            .state('manage.vendor_payment.view', {
                abstract: true,
                url: '',
                templateUrl: 'accounts_payable/views/payments/view.html',
                controller: 'ViewVendorPaymentController',
            })
            .state('manage.vendor_payment.view.summary', {
                url: '',
                templateUrl: 'accounts_payable/views/payments/summary.html',
            })
            .state('manage.vendor_payment.view.history', {
                url: '/history',
                templateUrl: 'events/views/history.html',
                controller: 'ObjectHistoryController',
            })
            // Payment Batches
            .state('manage.vendor_payment_batches', {
                abstract: true,
                url: '/vendor_payment_batches',
                template: '<ui-view/>',
            })
            .state('manage.vendor_payment_batches.browse', {
                url: '',
                templateUrl: 'accounts_payable/views/payment_batches/browse.html',
                controller: 'BrowseVendorPaymentBatchesController',
                reloadOnSearch: false,
            })
            .state('manage.vendor_payment_batches.new', {
                url: '/new',
                templateUrl: 'accounts_payable/views/payment_batches/new.html',
                controller: 'NewVendorPaymentBatchController',
                resolve: {
                    allowed: allowed('vendor_payments.create'),
                },
            })
            .state('manage.vendor_payment_batches.summary', {
                url: '/:id',
                templateUrl: 'accounts_payable/views/payment_batches/summary.html',
                controller: 'ViewVendorPaymentBatchController',
                reloadOnSearch: false,
            })
            // Approval Workflows
            .state('manage.approval_workflows', {
                abstract: true,
                url: '/approval_workflows',
                template: '<ui-view/>',
            })
            .state('manage.approval_workflows.new', {
                url: '/new',
                templateUrl: 'accounts_payable/views/approval_workflows/new.html',
                controller: 'NewApprovalWorkflowController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.approval_workflows.edit', {
                url: '/:id',
                templateUrl: 'accounts_payable/views/approval_workflows/new.html',
                controller: 'NewApprovalWorkflowController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
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
