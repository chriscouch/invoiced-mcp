(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('BrowseTransactionsController', BrowseTransactionsController);

    BrowseTransactionsController.$inject = [
        '$scope',
        '$rootScope',
        '$state',
        '$modal',
        '$translate',
        'LeavePageWarning',
        'Transaction',
        'Core',
        'localStorageService',
        'UiFilterService',
        'TableView',
    ];

    function BrowseTransactionsController(
        $scope,
        $rootScope,
        $state,
        $modal,
        $translate,
        LeavePageWarning,
        Transaction,
        Core,
        lss,
        UiFilterService,
        TableView,
    ) {
        $scope.table = new TableView({
            modelType: 'transaction',
            titlePlural: 'Transactions',
            titleSingular: 'Transaction',
            icon: '/img/event-icons/transaction.png',
            defaultSort: 'date DESC',
            titleMenu: [
                {
                    title: 'Disbursements',
                    route: 'manage.flywire_disbursements.browse',
                    allFeatures: ['flywire'],
                },
                {
                    title: 'Payments',
                    route: 'manage.payments.browse',
                },
                {
                    title: 'Payment Batches',
                    route: 'manage.customer_payment_batches.browse',
                    allFeatures: ['direct_ach'],
                },
                {
                    title: 'Remittance Advice',
                    route: 'manage.remittance_advices.browse',
                },
                {
                    title: 'Transactions',
                    route: 'manage.transactions.browse',
                },
            ],
            actions: [
                {
                    name: 'Import',
                    classes: 'btn btn-default hidden-xs hidden-sm',
                    allPermissions: ['imports.create', 'payments.create'],
                    perform: function () {
                        $state.go('manage.imports.new.spreadsheet', { type: 'transaction' });
                    },
                },
                {
                    name: 'Receive Payment',
                    classes: 'btn btn-success',
                    somePermissions: ['charges.create', 'payments.create'],
                    perform: newPayment,
                },
            ],
            exportable: true,
            findAllMethod: Transaction.findAll,
            buildRequest: function (table) {
                return {
                    advanced_filter: UiFilterService.serializeFilter(table.filter, table.filterFields),
                    sort: table.sort,
                };
            },
            transformResult: function (transactions) {
                angular.forEach(transactions, function (transaction) {
                    transaction.customer = transaction.customerName;
                });

                return transactions;
            },
            clickRow: function (transaction) {
                $state.go('manage.transaction.view.summary', { id: transaction.id });
            },
            customFields: true,
            columns: [
                {
                    id: 'amount',
                    name: 'Amount',
                    type: 'money',
                    default: true,
                    defaultOrder: 6,
                },
                {
                    id: 'created_at',
                    name: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'credit_note',
                    name: 'Credit Note',
                    type: 'string',
                },
                {
                    id: 'currency',
                    name: 'Currency',
                    type: 'enum',
                    values: UiFilterService.getCurrencyChoices(),
                },
                {
                    id: 'customer',
                    name: 'Customer',
                    type: 'customer',
                    sortId: 'Customers.name',
                    default: true,
                    defaultOrder: 1,
                },
                {
                    id: 'date',
                    name: 'Date',
                    type: 'date',
                    default: true,
                    defaultOrder: 3,
                },
                {
                    id: 'document_number',
                    name: 'Description',
                    type: 'string',
                    filterable: false,
                    sortable: false,
                    default: true,
                    defaultOrder: 2,
                },
                {
                    id: 'estimate',
                    name: 'Estimate',
                    type: 'string',
                },
                {
                    id: 'failure_reason',
                    name: 'Failure Reason',
                    type: 'string',
                },
                {
                    id: 'gateway',
                    name: 'Payment Gateway',
                    type: 'string',
                },
                {
                    id: 'gateway_id',
                    name: 'Transaction ID',
                    type: 'string',
                },
                {
                    id: 'id',
                    name: 'ID',
                    type: 'string',
                    filterable: false,
                },
                {
                    id: 'invoice',
                    name: 'Invoice',
                    type: 'string',
                },
                {
                    id: 'method',
                    name: 'Method',
                    default: true,
                    defaultOrder: 4,
                    type: 'enum',
                    values: [
                        { value: 'ach', text: $translate.instant('payment_method.ach') },
                        { value: 'balance', text: $translate.instant('payment_method.balance') },
                        { value: 'bank_transfer', text: $translate.instant('payment_method.bank_transfer') },
                        { value: 'cash', text: $translate.instant('payment_method.cash') },
                        { value: 'check', text: $translate.instant('payment_method.check') },
                        { value: 'credit_card', text: $translate.instant('payment_method.credit_card') },
                        { value: 'direct_debit', text: $translate.instant('payment_method.direct_debit') },
                        { value: 'eft', text: $translate.instant('payment_method.eft') },
                        { value: 'online', text: $translate.instant('payment_method.online') },
                        { value: 'other', text: $translate.instant('payment_method.other') },
                        { value: 'paypal', text: $translate.instant('payment_method.paypal') },
                        { value: 'wire_transfer', text: $translate.instant('payment_method.wire_transfer') },
                    ],
                },
                {
                    id: 'notes',
                    name: 'Notes',
                    type: 'string',
                    filterable: false,
                },
                {
                    id: 'payment',
                    name: 'Payment',
                    type: 'string',
                },
                {
                    id: 'sent',
                    name: 'Sent',
                    type: 'boolean',
                },
                {
                    id: 'status',
                    name: 'Status',
                    default: true,
                    defaultOrder: 5,
                    type: 'enum',
                    values: [
                        {
                            value: 'failed',
                            text: 'Failed',
                        },
                        {
                            value: 'pending',
                            text: 'Pending',
                        },
                        {
                            value: 'succeeded',
                            text: 'Succeeded',
                        },
                    ],
                },
                {
                    id: 'type',
                    name: 'Type',
                    default: true,
                    defaultOrder: 5,
                    type: 'enum',
                    values: [
                        {
                            value: 'charge',
                            text: 'Charge',
                        },
                        {
                            value: 'payment',
                            text: 'Payment',
                        },
                        {
                            value: 'refund',
                            text: 'Refund',
                        },
                        {
                            value: 'adjustment',
                            text: 'Adjustment',
                        },
                    ],
                },
                {
                    id: 'updated_at',
                    name: 'Updated At',
                    type: 'datetime',
                },
            ],
        });
        $scope.table.initialize();

        lss.set('goToTransactionsPage', 1);
        $rootScope.$broadcast('updatePaymentsPage');

        function newPayment() {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payments/receive-payment.html',
                controller: 'ReceivePaymentController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    options: function () {
                        return {};
                    },
                },
            });

            modalInstance.result.then(
                function (payment) {
                    LeavePageWarning.unblock();

                    Core.flashMessage('Your payment has been recorded', 'success');

                    if (payment.object === 'transaction') {
                        $state.go('manage.transaction.view.summary', {
                            id: payment.id,
                        });
                    } else {
                        $state.go('manage.payment.view.summary', {
                            id: payment.id,
                        });
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        }
    }
})();
