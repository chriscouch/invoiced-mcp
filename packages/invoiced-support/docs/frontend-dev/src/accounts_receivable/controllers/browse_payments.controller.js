/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('BrowsePaymentsController', BrowsePaymentsController);

    BrowsePaymentsController.$inject = [
        '$scope',
        '$rootScope',
        '$state',
        '$controller',
        '$modal',
        '$filter',
        '$translate',
        '$timeout',
        '$window',
        '$q',
        'LeavePageWarning',
        'Payment',
        'Money',
        'Core',
        'selectedCompany',
        'localStorageService',
        'ColumnArrangementService',
        'Metadata',
        'Feature',
        'UiFilterService',
        'AutomationWorkflow',
    ];

    function BrowsePaymentsController(
        $scope,
        $rootScope,
        $state,
        $controller,
        $modal,
        $filter,
        $translate,
        $timeout,
        $window,
        $q,
        LeavePageWarning,
        Payment,
        Money,
        Core,
        selectedCompany,
        lss,
        ColumnArrangementService,
        Metadata,
        Feature,
        UiFilterService,
        AutomationWorkflow,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Payment;
        $scope.modelTitleSingular = 'Payment';
        $scope.modelTitlePlural = 'Payments';
        $scope.modelListName = 'payments';

        $scope.transactionUrl = '/transactions' + $window.location.search;
        $scope.suggestTransactions = $window.location.search.indexOf('suggestTransactions') !== -1;

        //
        // Presets
        //

        $scope.payments = [];
        $scope.allColumns = ColumnArrangementService.getColumnsFromConfig('payment');

        //
        // Methods
        //
        $scope.loadSettings = function () {
            $q.all([loadSettings()]);
        };

        $scope.preFindAll = function () {
            $scope.loadSettings();

            if ($scope.customFields) {
                return buildFindParams($scope.filter, $scope.columns);
            }

            $q.all([$scope.loadCustomFields('payment', true)]);
            return buildFindParams($scope.filter, $scope.columns);
        };

        $scope.postFindAll = function (payments) {
            angular.forEach(payments, function (payment) {
                payment.applied = payment.balance <= 0;
            });

            $scope.payments = payments;
        };

        $scope.filterFields = function () {
            let fields = [
                {
                    id: 'amount',
                    label: 'Amount',
                    type: 'money',
                },
                {
                    id: 'automation',
                    label: 'Automation',
                    type: 'enum',
                    values: $scope.automations,
                    serialize: false,
                },
                {
                    id: 'balance',
                    label: 'Balance',
                    type: 'money',
                },
                {
                    id: 'created_at',
                    label: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'currency',
                    label: 'Currency',
                    type: 'enum',
                    values: UiFilterService.getCurrencyChoices(),
                },
                {
                    id: 'customer',
                    label: 'Customer',
                    type: 'customer',
                },
                {
                    id: 'date',
                    label: 'Date',
                    type: 'date',
                },
                {
                    id: 'date_voided',
                    label: 'Date Voided',
                    type: 'date',
                },
                {
                    id: 'matched',
                    label: 'Matched',
                    type: 'boolean',
                },
                {
                    id: 'reference',
                    label: 'Reference',
                    type: 'string',
                },
                {
                    id: 'updated_at',
                    label: 'Updated At',
                    type: 'datetime',
                },
                {
                    id: 'method',
                    label: $translate.instant('filter.method'),
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
                    id: 'source',
                    label: $translate.instant('filter.source'),
                    type: 'enum',
                    values: [
                        { value: 'accounting_system', text: $translate.instant('payments.source.accounting_system') },
                        { value: 'api', text: $translate.instant('payments.source.api') },
                        { value: 'autopay', text: $translate.instant('payments.source.autopay') },
                        { value: 'bank_feed', text: $translate.instant('payments.source.bank_feed') },
                        { value: 'check_lockbox', text: $translate.instant('payments.source.check_lockbox') },
                        { value: 'customer_portal', text: $translate.instant('payments.source.customer_portal') },
                        { value: 'imported', text: $translate.instant('payments.source.imported') },
                        { value: 'keyed', text: $translate.instant('payments.source.keyed') },
                        { value: 'network', text: $translate.instant('payments.source.network') },
                        { value: 'remittance_advice', text: $translate.instant('payments.source.remittance_advice') },
                        { value: 'virtual_terminal', text: $translate.instant('payments.source.virtual_terminal') },
                    ],
                },
                {
                    id: 'applied',
                    label: $translate.instant('filter.applied'),
                    type: 'boolean',
                    displayInFilterString: function (filter) {
                        return filter.voided !== '0' || filter.applied !== '0';
                    },
                },
                {
                    id: 'voided',
                    label: $translate.instant('filter.voided'),
                    type: 'boolean',
                    displayInFilterString: function (filter) {
                        return filter.voided !== '0' || filter.applied !== '0';
                    },
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'date DESC',
                    values: [
                        { value: 'amount ASC', text: $translate.instant('filter.amount_asc') },
                        { value: 'amount DESC', text: $translate.instant('filter.amount_desc') },
                        { value: 'date ASC', text: $translate.instant('filter.date_asc') },
                        { value: 'date DESC', text: $translate.instant('filter.date_desc') },
                        { value: 'method ASC', text: $translate.instant('filter.method') },
                        { value: 'created_at ASC', text: $translate.instant('filter.created_asc') },
                        { value: 'created_at DESC', text: $translate.instant('filter.created_desc') },
                    ],
                },
            ];

            return fields.concat(UiFilterService.buildCustomFieldFilters($scope.customFields));
        };

        $scope.noResults = function () {
            return $scope.payments.length === 0;
        };

        $scope.newPaymentModal = function () {
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
        };

        $scope.sendReceipt = function (payment) {
            const modalInstance = $modal.open({
                templateUrl: 'sending/views/send-document.html',
                controller: 'SendDocumentController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                windowClass: 'send-document-modal',
                resolve: {
                    model: function () {
                        return $scope.model;
                    },
                    _document: function () {
                        return payment;
                    },
                    paymentPlan: function () {
                        return null;
                    },
                    customerId: function () {
                        return payment.customer;
                    },
                    sendOptions: function () {
                        return {};
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    Core.flashMessage(result, 'success');
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.void = function (payment) {
            vex.dialog.confirm({
                message: $scope.voidMessage(payment),
                callback: function (result) {
                    if (result) {
                        _void(payment);
                    }
                },
            });
        };

        $scope.voidMessage = function (payment) {
            let customerName = payment.customerName;
            let escapeHtml = $filter('escapeHtml');

            return (
                '<p>Are you sure you want to void this payment? All applications of this payment will be permanently deleted.</p>' +
                '<p>Customer: ' +
                escapeHtml(customerName) +
                '<br/>' +
                'Amount: ' +
                Money.currencyFormat(payment.amount, payment.currency, $scope.company.moneyFormat) +
                '<br/>' +
                'Date: ' +
                $filter('formatCompanyDate')(payment.date) +
                '<br/>' +
                'Method: ' +
                escapeHtml(payment.method) +
                '</p>'
            );
        };

        function _void(payment) {
            $scope.deleting = true;

            let params = {
                id: payment.id,
            };

            $scope.model.delete(
                params,
                function (updatedPayment) {
                    $scope.deleting = false;
                    updatedPayment.applied = false;
                    angular.extend(payment, updatedPayment);
                },
                function (result) {
                    $scope.deleting = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        $scope.export = function () {
            // use the same query parameters as the list endpoint
            let params = buildFindParams($scope.filter, $scope.columns);

            $modal.open({
                templateUrl: 'exports/views/export.html',
                controller: 'ExportController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    type: function () {
                        return 'payment';
                    },
                    options: function () {
                        return params;
                    },
                },
            });
        };

        $scope.automate = function () {
            $modal.open({
                templateUrl: 'automations/views/automate-mass-object.html',
                controller: 'AutomateMassObjectController',
                resolve: {
                    objectType: () => 'payment',
                    options: () => buildFindParams($scope.filter, []),
                    count: () => $scope.total_count,
                },
            });
        };

        //
        // Initialization
        //

        $scope.initializeListPage();

        Core.setTitle('Payments');
        loadAutomations();

        lss.set('goToTransactionsPage', 0);
        $rootScope.$broadcast('updatePaymentsPage');

        function buildFindParams(input, columns) {
            let include = 'customerName,bank_account_name';
            if ($scope.tableHasMetadata(columns)) {
                include += ',metadata';
            }
            const params = {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter(input, $scope._filterFields),
                include: include,
                sort: input.sort,
            };

            if (input.automation.value) {
                params.automation = input.automation.value;
            }
            return params;
        }

        function loadSettings() {
            return $q(function (resolve) {
                $scope.columns = ColumnArrangementService.getSelectedColumns('payment', $scope.allColumns);
                resolve();
            });
        }

        function loadAutomations() {
            AutomationWorkflow.loadAutomations(
                'payment',
                automations => {
                    $scope.automations = automations;
                    $scope.generateFilterFields();
                    $scope.updateFilterString();
                },
                () => {},
            );
        }
    }
})();
