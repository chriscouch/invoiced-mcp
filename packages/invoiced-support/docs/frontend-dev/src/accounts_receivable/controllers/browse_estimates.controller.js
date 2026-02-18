/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('BrowseEstimatesController', BrowseEstimatesController);

    BrowseEstimatesController.$inject = [
        '$scope',
        '$state',
        '$controller',
        '$rootScope',
        '$modal',
        '$filter',
        '$q',
        '$translate',
        'Estimate',
        'Invoice',
        'Money',
        'Core',
        'Feature',
        'ColumnArrangementService',
        'UiFilterService',
        'AutomationWorkflow',
    ];

    function BrowseEstimatesController(
        $scope,
        $state,
        $controller,
        $rootScope,
        $modal,
        $filter,
        $q,
        $translate,
        Estimate,
        Invoice,
        Money,
        Core,
        Feature,
        ColumnArrangementService,
        UiFilterService,
        AutomationWorkflow,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Estimate;
        $scope.modelTitleSingular = 'Estimate';
        $scope.modelTitlePlural = 'Estimates';

        //
        // Presets
        //

        $scope.estimates = [];
        $scope.allColumns = ColumnArrangementService.getColumnsFromConfig('estimate');

        //
        // Methods
        //
        $scope.loadSettings = function () {
            $q.all([loadSettings()]);
        };

        /* Estimate Browsing Methods */
        $scope.preFindAll = function () {
            $scope.loadSettings();

            if ($scope.customFields) {
                return buildFindParams($scope.filter, $scope.columns);
            }

            $q.all([$scope.loadCustomFields('estimate', true)]);
            return buildFindParams($scope.filter, $scope.columns);
        };

        $scope.postFindAll = function (estimates) {
            $scope.estimates = estimates;
        };

        $scope.filterFields = function () {
            let fields = [
                {
                    id: 'approved',
                    label: 'Approved By',
                    type: 'string',
                },
                {
                    id: 'automation',
                    label: 'Automation',
                    type: 'enum',
                    values: $scope.automations,
                    serialize: false,
                },
                {
                    id: 'closed',
                    label: 'Closed',
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
                    id: 'date_voided',
                    label: 'Date Voided',
                    type: 'date',
                },
                {
                    id: 'deposit',
                    label: 'Deposit',
                    type: 'money',
                },
                {
                    id: 'deposit_paid',
                    label: 'Deposit Paid',
                    type: 'boolean',
                },
                {
                    id: 'draft',
                    label: 'Draft',
                    type: 'boolean',
                },
                {
                    id: 'expiration_date',
                    label: 'Expiration Date',
                    type: 'date',
                },
                {
                    id: 'name',
                    label: 'Estimate Name',
                    type: 'string',
                },
                {
                    id: 'number',
                    label: 'Estimate #',
                    type: 'string',
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
                    defaultValue: 'open',
                    values: [
                        { value: 'draft', text: 'Draft' },
                        { value: 'open', text: 'Open' },
                        { value: 'expired', text: 'Expired' },
                        { value: 'approved', text: 'Approved' },
                        { value: 'invoiced', text: 'Invoiced' },
                        { value: 'declined', text: 'Declined' },
                        { value: 'voided', text: 'Voided' },
                    ],
                    displayInFilterString: function (filter) {
                        return filter.status === 'declined' || filter.status === 'voided';
                    },
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'date DESC',
                    values: [
                        { value: 'number ASC', text: 'Estimate #, Ascending Order' },
                        { value: 'number DESC', text: 'Estimate #, Descending Order' },
                        { value: 'Customers.name ASC', text: 'Customer, Ascending Order' },
                        { value: 'Customers.name DESC', text: 'Customer, Descending Order' },
                        { value: 'date ASC', text: 'Date, Oldest First' },
                        { value: 'date DESC', text: 'Date, Newest First' },
                        { value: 'total ASC', text: 'Total, Lowest First' },
                        { value: 'total DESC', text: 'Total, Highest First' },
                        { value: 'balance ASC', text: 'Balance, Lowest First' },
                        { value: 'balance DESC', text: 'Balance, Highest First' },
                        { value: 'status ASC', text: 'Status, Descending Order' },
                        { value: 'status DESC', text: 'Status, Ascending Order' },
                        { value: 'created_at ASC', text: 'Created Date, Oldest First' },
                        { value: 'created_at DESC', text: 'Created Date, Newest First' },
                    ],
                },
            ];

            return fields.concat(UiFilterService.buildCustomFieldFilters($scope.customFields));
        };

        $scope.noResults = function () {
            return $scope.estimates.length === 0;
        };

        $scope.issue = function (estimate) {
            vex.dialog.confirm({
                message: $scope.issueMessage(estimate),
                callback: function (result) {
                    if (result) {
                        _issue(estimate);
                    }
                },
            });
        };

        $scope.void = function (estimate) {
            vex.dialog.confirm({
                message: $scope.voidMessage(estimate),
                callback: function (result) {
                    if (result) {
                        _void(estimate);
                    }
                },
            });
        };

        $scope.makeInvoiceFromEstimate = function (estimate) {
            $scope.saving = true;

            Estimate.makeInvoiceFromEstimate(
                {
                    id: estimate.id,
                },
                function (invoice) {
                    $scope.saving = false;

                    $state.go('manage.invoice.view.summary', {
                        id: invoice.id,
                    });
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.emailModal = function (estimate) {
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
                        return estimate;
                    },
                    paymentPlan: function () {
                        return null;
                    },
                    customerId: function () {
                        return estimate.customer;
                    },
                    sendOptions: function () {
                        return {};
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    Core.flashMessage(result, 'success');
                    if (estimate.status === 'not_sent') {
                        estimate.status = 'sent';
                    }
                    $scope.sendEstimate = false;
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.issueMessage = function (estimate) {
            return '<p>Are you sure you want to issue this estimate?</p>' + estimateMessageDetails(estimate);
        };

        function _issue(estimate) {
            $scope.issuing = true;

            Estimate.edit(
                {
                    id: estimate.id,
                },
                {
                    draft: false,
                },
                function (updatedEstimate) {
                    $scope.issuing = false;
                    angular.extend(estimate, updatedEstimate);
                },
                function (result) {
                    $scope.issuing = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        $scope.voidMessage = function (estimate) {
            return '<p>Are you sure you want to void this estimate?</p>' + estimateMessageDetails(estimate);
        };

        function estimateMessageDetails(estimate) {
            let escapeHtml = $filter('escapeHtml');
            let customerName = estimate.customer.name || estimate.customerName;
            return (
                '<p><strong>' +
                escapeHtml(estimate.name) +
                ' <small>' +
                escapeHtml(estimate.number) +
                '</small></strong><br/>' +
                'Customer: ' +
                escapeHtml(customerName) +
                '<br/>' +
                'Total: ' +
                Money.currencyFormat(estimate.total, estimate.currency, $scope.company.moneyFormat) +
                '<br/>' +
                'Date: ' +
                $filter('formatCompanyDate')(estimate.date) +
                '</p>'
            );
        }

        function _void(estimate) {
            $scope.deleting = true;

            Estimate.void(
                {
                    id: estimate.id,
                },
                function (updatedEstimate) {
                    $scope.deleting = false;
                    angular.extend(estimate, updatedEstimate);
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
                        return 'estimate';
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
                    objectType: () => 'estimate',
                    options: () => buildFindParams($scope.filter, []),
                    count: () => $scope.total_count,
                },
            });
        };

        //
        // Initialization
        //

        $scope.initializeListPage();
        Core.setTitle('Estimates');
        loadAutomations();

        function buildFindParams(input, columns) {
            let params = {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter(input, $scope._filterFields),
                sort: input.sort,
                include: $scope.tableHasMetadata(columns) ? 'customerName,metadata' : 'customerName',
            };

            if (input.status.value === 'open') {
                params.filter.closed = 0;
                params.filter.draft = 0;
                params.filter.voided = 0;
            } else if (input.status.value) {
                params.filter.status = input.status.value;
            }

            if (input.automation.value) {
                params.automation = input.automation.value;
            }

            return params;
        }

        function loadSettings() {
            return $q(function (resolve) {
                $scope.columns = ColumnArrangementService.getSelectedColumns('estimate', $scope.allColumns);
                resolve();
            });
        }

        function loadAutomations() {
            AutomationWorkflow.loadAutomations(
                'estimate',
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
