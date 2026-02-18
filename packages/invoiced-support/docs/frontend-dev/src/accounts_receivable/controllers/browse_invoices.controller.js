/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('BrowseInvoicesController', BrowseInvoicesController);

    BrowseInvoicesController.$inject = [
        '$scope',
        '$controller',
        '$rootScope',
        '$modal',
        '$filter',
        '$state',
        '$q',
        '$translate',
        'Invoice',
        'Money',
        'Core',
        'CustomField',
        'selectedCompany',
        'Permission',
        'Feature',
        'ColumnArrangementService',
        'DatePickerService',
        'InvoiceChasingCadence',
        'Metadata',
        'UiFilterService',
        'AutomationWorkflow',
    ];

    function BrowseInvoicesController(
        $scope,
        $controller,
        $rootScope,
        $modal,
        $filter,
        $state,
        $q,
        $translate,
        Invoice,
        Money,
        Core,
        CustomField,
        selectedCompany,
        Permission,
        Feature,
        ColumnArrangementService,
        DatePickerService,
        InvoiceChasingCadence,
        Metadata,
        UiFilterService,
        AutomationWorkflow,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Invoice;
        $scope.modelTitleSingular = 'Invoice';
        $scope.modelTitlePlural = 'Invoices';

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.hasSendingPermissions = Permission.hasSomePermissions([
            'text_messages.send',
            'letters.send',
            'emails.send',
        ]);

        //
        // Presets
        //

        $scope.invoices = [];
        $scope.cadences = [];
        $scope.allColumns = ColumnArrangementService.getColumnsFromConfig('invoice');

        //
        // Methods
        //
        $scope.loadSettings = function () {
            $q.all([loadSettings()]);
        };

        /* Invoice Browsing Methods */
        $scope.preFindAll = function () {
            $scope.loadSettings();
            if ($scope.customFields) {
                return buildFindParams($scope.filter, $scope.columns);
            }

            $q.all([$scope.loadCustomFields('invoice', true)]);
            return buildFindParams($scope.filter, $scope.columns);
        };

        $scope.postFindAll = function (invoices) {
            $scope.invoices = invoices;

            let max = 0;
            angular.forEach(invoices, function (invoice) {
                max = Math.max(max, invoice.balance);
            });
            $scope.outstandingMax = max;
        };

        $scope.filterFields = function () {
            let fields = [
                {
                    id: 'amount_credited',
                    label: 'Applied Credits',
                    type: 'money',
                },
                {
                    id: 'amount_paid',
                    label: 'Amount Paid',
                    type: 'money',
                },
                {
                    id: 'amount_written_off',
                    label: 'Bad Debt Written Off',
                    type: 'money',
                },
                {
                    id: 'attempt_count',
                    label: 'Attempt Count',
                    type: 'number',
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
                    id: 'closed',
                    label: 'Closed',
                    type: 'boolean',
                },
                {
                    id: 'consolidated',
                    label: 'Consolidated',
                    type: 'boolean',
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
                    id: 'date_bad_debt',
                    label: 'Date Bad Debt',
                    type: 'date',
                },
                {
                    id: 'date_paid',
                    label: 'Date Paid',
                    type: 'date',
                },
                {
                    id: 'date_voided',
                    label: 'Date Voided',
                    type: 'date',
                },
                {
                    id: 'draft',
                    label: 'Draft',
                    type: 'boolean',
                },
                {
                    id: 'due_date',
                    label: 'Due Date',
                    type: 'date',
                },
                {
                    id: 'late_fees',
                    label: 'Late Fees',
                    type: 'boolean',
                },
                {
                    id: 'name',
                    label: 'Invoice Name',
                    type: 'string',
                },
                {
                    id: 'needs_attention',
                    label: 'Needs Attention',
                    type: 'boolean',
                },
                {
                    id: 'next_payment_attempt',
                    label: 'AutoPay Payment Date',
                    type: 'date',
                },
                {
                    id: 'number',
                    label: 'Invoice #',
                    type: 'string',
                },
                {
                    id: 'paid',
                    label: 'Paid',
                    type: 'boolean',
                },
                {
                    id: 'payment_terms',
                    label: 'Payment Terms',
                    type: 'string',
                },
                {
                    id: 'purchase_order',
                    label: 'Purchase Order',
                    type: 'string',
                },
                {
                    id: 'sent',
                    label: 'Sent',
                    type: 'boolean',
                },
                {
                    id: 'subtotal',
                    label: 'Subtotal',
                    type: 'money',
                },
                {
                    id: 'total',
                    label: 'Total',
                    type: 'money',
                },
                {
                    id: 'updated_at',
                    label: 'Updated At',
                    type: 'datetime',
                },
                {
                    id: 'viewed',
                    label: 'Viewed',
                    type: 'boolean',
                },
                {
                    id: 'voided',
                    label: 'Voided',
                    type: 'boolean',
                },
                {
                    id: 'status',
                    label: 'Status',
                    serialize: false,
                    type: 'enum',
                    defaultValue: 'unpaid',
                    values: [
                        { value: 'draft', text: 'Draft' },
                        { value: 'unpaid', text: 'Outstanding' },
                        { value: 'past_due', text: 'Past Due' },
                        { value: 'pending', text: 'Payment pending' },
                        { value: 'paid', text: 'Paid' },
                        { value: 'bad_debt', text: 'Bad Debt' },
                        { value: 'voided', text: 'Voided' },
                        { value: 'broken_promise', text: 'Broken Promise' },
                    ],
                    displayInFilterString: function (filter) {
                        return (
                            filter.status === 'pending' ||
                            filter.status === 'bad_debt' ||
                            filter.status === 'broken_promise' ||
                            filter.status === 'voided'
                        );
                    },
                },
                {
                    id: 'autopay',
                    label: 'AutoPay',
                    serialize: false,
                    type: 'enum',
                    values: [
                        { value: '0', text: 'No AutoPay' },
                        { value: '1', text: 'Has AutoPay' },
                        { value: 'no_payment_info', text: 'Has AutoPay without payment info' },
                    ],
                },
                {
                    id: 'payment_plan',
                    label: 'Payment Plan',
                    serialize: false,
                    type: 'enum',
                    values: [
                        { value: '0', text: 'No Payment Plan' },
                        { value: '1', text: 'Has Payment Plan' },
                        { value: 'needs_approval', text: 'Payment Plan Needs Approval' },
                    ],
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'date ASC',
                    values: [
                        { value: 'number ASC', text: 'Invoice #, Ascending Order' },
                        { value: 'number DESC', text: 'Invoice #, Descending Order' },
                        { value: 'Customers.name ASC', text: 'Customer, Ascending Order' },
                        { value: 'Customers.name DESC', text: 'Customer, Descending Order' },
                        { value: 'date ASC', text: 'Date, Oldest First' },
                        { value: 'date DESC', text: 'Date, Newest First' },
                        { value: 'due_date ASC', text: 'Due Date, Oldest First' },
                        { value: 'due_date DESC', text: 'Due Date, Newest First' },
                        { value: 'total ASC', text: 'Total, Lowest First' },
                        { value: 'total DESC', text: 'Total, Highest First' },
                        { value: 'balance ASC', text: 'Balance, Lowest First' },
                        { value: 'balance DESC', text: 'Balance, Highest First' },
                        { value: 'status ASC', text: 'Status, Descending Order' },
                        { value: 'status DESC', text: 'Status, Ascending Order' },
                        { value: 'next_payment_attempt ASC', text: 'AutoPay Date, Soonest First' },
                        { value: 'next_payment_attempt DESC', text: 'AutoPay Date, Latest First' },
                        { value: 'created_at ASC', text: 'Created Date, Oldest First' },
                        { value: 'created_at DESC', text: 'Created Date, Newest First' },
                    ],
                },
            ];

            if (Feature.hasFeature('invoice_tags')) {
                fields.push({
                    id: 'tags',
                    label: 'Tags',
                    defaultValue: [],
                    serialize: false,
                    type: 'invoice_tags',
                });
            }

            if (Feature.hasFeature('smart_chasing')) {
                fields.push({
                    id: 'chasing',
                    label: 'Chasing',
                    serialize: false,
                    type: 'boolean',
                });
                fields.push({
                    id: 'chasingCadence',
                    label: 'Chasing Cadence',
                    serialize: false,
                    type: 'enum',
                    values: $scope.cadences,
                });
            }

            if (Feature.hasFeature('subscriptions')) {
                fields.push({
                    id: 'subscription',
                    label: 'Subscription ID',
                    type: 'string',
                });
            }

            return fields.concat(UiFilterService.buildCustomFieldFilters($scope.customFields));
        };

        $scope.noResults = function () {
            return $scope.invoices.length === 0;
        };

        $scope.issue = function (invoice) {
            vex.dialog.confirm({
                message: $scope.issueMessage(invoice),
                callback: function (result) {
                    if (result) {
                        _issue(invoice);
                    }
                },
            });
        };

        $scope.void = function (invoice) {
            vex.dialog.confirm({
                message: $scope.voidMessage(invoice),
                callback: function (result) {
                    if (result) {
                        _void(invoice);
                    }
                },
            });
        };

        $scope.sendModal = function (invoice) {
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
                        return invoice;
                    },
                    paymentPlan: function () {
                        return Invoice.paymentPlan({ id: invoice.id });
                    },
                    customerId: function () {
                        return invoice.customer;
                    },
                    sendOptions: function () {
                        return {};
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    Core.flashMessage(result, 'success');
                    if (invoice.status === 'not_sent') {
                        invoice.status = 'sent';
                    }
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.paymentModal = function (invoice) {
            let customer =
                typeof invoice.customer === 'object'
                    ? invoice.customer
                    : {
                          id: invoice.customer,
                          name: invoice.customerName,
                      };

            // Reload the invoice to ensure we have the latest balance
            Invoice.find(
                {
                    id: invoice.id,
                },
                {
                    exclude: 'items,discounts,taxes,shipping,ship_to,payment_source,metadata',
                },
                function (invoice2) {
                    const modalInstance = $modal.open({
                        templateUrl: 'accounts_receivable/views/payments/receive-payment.html',
                        controller: 'ReceivePaymentController',
                        backdrop: 'static',
                        keyboard: false,
                        size: 'lg',
                        resolve: {
                            options: function () {
                                return {
                                    customer: customer,
                                    preselected: [invoice2],
                                    currency: invoice2.currency,
                                    amount: invoice2.balance,
                                };
                            },
                        },
                    });

                    modalInstance.result.then(
                        function () {
                            Core.flashMessage('Your payment has been recorded', 'success');
                        },
                        function () {
                            // canceled
                        },
                    );
                },
                function (result) {
                    Core.showMessage(result.data.message);
                },
            );
        };

        $scope.issueMessage = function (invoice) {
            return '<p>Are you sure you want to issue this invoice?</p>' + invoiceMessageDetails(invoice);
        };

        function _issue(invoice) {
            $scope.issuing = true;

            Invoice.edit(
                {
                    id: invoice.id,
                },
                {
                    draft: false,
                },
                function (updatedInvoice) {
                    $scope.issuing = false;
                    angular.extend(invoice, updatedInvoice);
                },
                function (result) {
                    $scope.issuing = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        $scope.voidMessage = function (invoice) {
            return '<p>Are you sure you want to void this invoice?</p>' + invoiceMessageDetails(invoice);
        };

        function invoiceMessageDetails(invoice) {
            let escapeHtml = $filter('escapeHtml');
            let customerName = invoice.customer.name || invoice.customerName;
            return (
                '<p><strong>' +
                escapeHtml(invoice.name) +
                ' <small>' +
                escapeHtml(invoice.number) +
                '</small></strong><br/>' +
                'Customer: ' +
                escapeHtml(customerName) +
                '<br/>' +
                'Total: ' +
                Money.currencyFormat(invoice.total, invoice.currency, $scope.company.moneyFormat) +
                '<br/>' +
                'Date: ' +
                $filter('formatCompanyDate')(invoice.date) +
                '</p>'
            );
        }

        function _void(invoice) {
            $scope.deleting = true;

            Invoice.void(
                {
                    id: invoice.id,
                },
                function (updatedInvoice) {
                    $scope.deleting = false;
                    angular.extend(invoice, updatedInvoice);
                },
                function (result) {
                    $scope.deleting = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        $scope.export = function (format, detailLevel) {
            // use the same query parameters as the list endpoint
            let params = buildFindParams($scope.filter, $scope.columns);
            params.type = format;
            params.detail = detailLevel;

            $modal.open({
                templateUrl: 'exports/views/export.html',
                controller: 'ExportController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    type: function () {
                        if (detailLevel === 'payment_plan') {
                            return 'payment_plan';
                        }

                        return 'invoice';
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
                    objectType: () => 'invoice',
                    options: () => buildFindParams($scope.filter, []),
                    count: () => $scope.total_count,
                },
            });
        };

        //
        // Initialization
        //
        $scope.loadSettings = function () {
            $q.all([loadSettings()]);
        };
        $scope.initializeListPage();
        loadChasingCadences();
        loadAutomations();

        Core.setTitle('Invoices');

        function loadChasingCadences() {
            if (!Feature.hasFeature('invoice_chasing')) {
                return;
            }

            InvoiceChasingCadence.findAll(
                { paginate: 'none' },
                function (cadences) {
                    $scope.cadences = [];
                    angular.forEach(cadences, function (cadence) {
                        // needed for ngOptions
                        cadence.id = '' + cadence.id;
                        $scope.cadences.push({ text: cadence.name, value: cadence.id });
                    });

                    // update filter definition and rebuild the filter string
                    $scope.generateFilterFields();
                    $scope.updateFilterString();
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadAutomations() {
            AutomationWorkflow.loadAutomations(
                'invoice',
                automations => {
                    $scope.automations = automations;
                    $scope.generateFilterFields();
                    $scope.updateFilterString();
                },
                () => {},
            );
        }

        function buildFindParams(input, columns) {
            let include = 'customerName';
            if ($scope.tableHasMetadata(columns)) {
                include += ',metadata';
                $scope.metadataLoaded = true;
            }
            if ($scope.tableHasColumn(columns, 'expected_payment_date')) {
                include += ',expected_payment_date';
            }
            let params = {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter(input, $scope._filterFields),
                sort: input.sort,
                include: include,
            };

            if (input.status.value === 'unpaid') {
                params.filter.paid = 0;
                params.filter.closed = 0;
                params.filter.draft = 0;
                params.filter.voided = 0;
            } else if (input.status.value === 'broken_promise') {
                params.filter.voided = 0;
                params.broken_promises = 1;
            } else if (input.status.value) {
                params.filter.status = input.status.value;
            }

            if (input.autopay.value === 'no_payment_info') {
                params.filter.autopay = '1';
                params.customer_payment_info = '0';
            } else if (input.autopay.value) {
                params.filter.autopay = input.autopay.value;
            }

            if (input.payment_plan.value) {
                params.payment_plan = input.payment_plan.value;
            }

            if (input.chasing) {
                params.chasing = input.chasing;
            }

            if (input.chasingCadence && input.chasingCadence.value) {
                params.cadence = input.chasingCadence.value;
            }

            if (input.tags instanceof Array && input.tags.length > 0) {
                params.tags = input.tags;
            }

            if (input.automation.value) {
                params.automation = input.automation.value;
            }

            return params;
        }

        function loadSettings() {
            return $q(function (resolve) {
                $scope.columns = ColumnArrangementService.getSelectedColumns('invoice', $scope.allColumns);
                resolve();
            });
        }
    }
})();
