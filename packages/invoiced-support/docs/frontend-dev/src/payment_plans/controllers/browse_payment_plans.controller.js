(function () {
    'use strict';

    angular.module('app.payment_plans').controller('BrowsePaymentPlansController', BrowsePaymentPlansController);

    BrowsePaymentPlansController.$inject = [
        '$scope',
        '$controller',
        '$modal',
        '$state',
        '$translate',
        '$q',
        'Invoice',
        'Money',
        'Core',
        'Metadata',
        'CustomField',
        'selectedCompany',
        'Feature',
        'UiFilterService',
        'AutomationWorkflow',
    ];

    function BrowsePaymentPlansController(
        $scope,
        $controller,
        $modal,
        $state,
        $translate,
        $q,
        Invoice,
        Money,
        Core,
        Metadata,
        CustomField,
        selectedCompany,
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

        $scope.model = Invoice;
        $scope.modelTitleSingular = 'Payment Plan';
        $scope.modelTitlePlural = 'Payment Plans';

        //
        // Presets
        //

        $scope.invoices = [];

        //
        // Methods
        //

        $scope.preFindAll = function () {
            if ($scope.customFields) {
                return buildFindParams($scope.filter);
            }

            $q.all([$scope.loadCustomFields('estimate', true)]);
            return buildFindParams($scope.filter);
        };

        $scope.postFindAll = function (invoices) {
            angular.forEach(invoices, function (invoice) {
                invoice.num_remaining_installments = 0;
                invoice.next_installment_date = null;
                invoice.start_date =
                    invoice.payment_plan.installments.length > 0
                        ? invoice.payment_plan.installments[0].date
                        : invoice.date;
                angular.forEach(invoice.payment_plan.installments, function (installment) {
                    if (installment.balance > 0) {
                        invoice.num_remaining_installments++;
                        if (!invoice.next_installment_date) {
                            invoice.next_installment_date = installment.date;
                        }
                    }
                });
            });

            $scope.invoices = invoices;
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
                        { value: 'needs_approval', text: 'Needs Approval' },
                        { value: 'unpaid', text: 'Outstanding' },
                        { value: 'past_due', text: 'Past Due' },
                        { value: 'paid', text: 'Paid' },
                    ],
                    displayInFilterString: false,
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

        $scope.export = function () {
            // use the same query parameters as the list endpoint
            let params = buildFindParams($scope.filter);

            $modal.open({
                templateUrl: 'exports/views/export.html',
                controller: 'ExportController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    type: function () {
                        return 'payment_plan';
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
                    objectType: () => 'payment_plan',
                    options: () => buildFindParams($scope.filter),
                    count: () => $scope.total_count,
                },
            });
        };

        //
        // Initialization
        //

        $scope.initializeListPage();
        Core.setTitle('Payment Plans');
        loadAutomations();

        function buildFindParams(input) {
            let params = {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter(input, $scope._filterFields),
                sort: input.sort,
                payment_plan: 1,
                include: 'customerName',
                expand: 'payment_plan',
            };

            if (input.status.value === 'unpaid') {
                params.filter.paid = 0;
                params.filter.closed = 0;
                params.filter.draft = 0;
                params.filter.voided = 0;
            } else if (input.status.value === 'needs_approval') {
                params.filter.paid = 0;
                params.filter.closed = 0;
                params.filter.draft = 0;
                params.filter.voided = 0;
                params.payment_plan = 'needs_approval';
            } else if (input.status.value) {
                params.filter.voided = 0;
                params.filter.status = input.status.value;
            }

            if (input.autopay.value === 'no_payment_info') {
                params.filter.autopay = '1';
                params.customer_payment_info = '0';
            } else if (input.autopay.value === '1') {
                params.filter.autopay = '1';
            } else if (input.autopay.value === '0') {
                params.filter.autopay = '0';
            }

            if (input.automation.value) {
                params.automation = input.automation.value;
            }

            return params;
        }

        function loadAutomations() {
            AutomationWorkflow.loadAutomations(
                'payment_plan',
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
