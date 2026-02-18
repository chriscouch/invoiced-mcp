/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('BrowseCreditNotesController', BrowseCreditNotesController);

    BrowseCreditNotesController.$inject = [
        '$scope',
        '$controller',
        '$rootScope',
        '$modal',
        '$filter',
        '$state',
        '$translate',
        '$q',
        'CreditNote',
        'Money',
        'Core',
        'CustomField',
        'Feature',
        'ColumnArrangementService',
        'Metadata',
        'UiFilterService',
        'AutomationWorkflow',
    ];

    function BrowseCreditNotesController(
        $scope,
        $controller,
        $rootScope,
        $modal,
        $filter,
        $state,
        $translate,
        $q,
        CreditNote,
        Money,
        Core,
        CustomField,
        Feature,
        ColumnArrangementService,
        Metadata,
        UiFilterService,
        AutomationWorkflow,
    ) {
        let escapeHtml = $filter('escapeHtml');
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = CreditNote;
        $scope.modelTitleSingular = 'Credit Note';
        $scope.modelTitlePlural = 'Credit Notes';

        //
        // Presets
        //

        $scope.creditNotes = [];
        $scope.allColumns = ColumnArrangementService.getColumnsFromConfig('credit_note');

        //
        // Methods
        //
        $scope.loadSettings = function () {
            $q.all([loadSettings()]);
        };

        /* Credit Note Browsing Methods */

        $scope.preFindAll = function () {
            $scope.loadSettings();

            if ($scope.customFields) {
                return buildFindParams($scope.filter, $scope.columns);
            }

            $q.all([$scope.loadCustomFields('credit_note', true)]);
            return buildFindParams($scope.filter, $scope.columns);
        };

        $scope.postFindAll = function (creditNotes) {
            $scope.creditNotes = creditNotes;

            let max = 0;
            angular.forEach(creditNotes, function (creditNote) {
                max = Math.max(max, creditNote.balance);
            });
            $scope.outstandingMax = max;
        };

        $scope.filterFields = function () {
            let fields = [
                {
                    id: 'amount_applied_to_invoice',
                    label: 'Amount Applied To Invoice',
                    type: 'money',
                },
                {
                    id: 'amount_credited',
                    label: 'Amount Credited',
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
                    id: 'draft',
                    label: 'Draft',
                    type: 'boolean',
                },
                {
                    id: 'name',
                    label: 'Credit Note Name',
                    type: 'string',
                },
                {
                    id: 'number',
                    label: 'Credit Note #',
                    type: 'string',
                },
                {
                    id: 'paid',
                    label: 'Paid',
                    type: 'boolean',
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
                        { value: 'paid', text: 'Paid' },
                        { value: 'closed', text: 'Closed' },
                        { value: 'voided', text: 'Voided' },
                    ],
                    displayInFilterString: function (filter) {
                        return filter.status === 'closed' || filter.status === 'voided';
                    },
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'date ASC',
                    values: [
                        { value: 'number ASC', text: 'Credit Note #, Ascending Order' },
                        { value: 'number DESC', text: 'Credit Note #, Descending Order' },
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
            return $scope.creditNotes.length === 0;
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

        $scope.void = function (creditNote) {
            vex.dialog.confirm({
                message: $scope.voidMessage(creditNote),
                callback: function (result) {
                    if (result) {
                        _void(creditNote);
                    }
                },
            });
        };

        $scope.issueMessage = function (creditNote) {
            return '<p>Are you sure you want to issue this credit note?</p>' + creditNoteMessage(creditNote);
        };

        $scope.voidMessage = function (creditNote) {
            return '<p>Are you sure you want to void this credit note?</p>' + creditNoteMessage(creditNote);
        };

        function creditNoteMessage(creditNote) {
            let customerName = creditNote.customer.name || creditNote.customerName;
            return (
                '<p><strong>' +
                escapeHtml(creditNote.name) +
                ' <small>' +
                escapeHtml(creditNote.number) +
                '</small></strong><br/>' +
                'Customer: ' +
                escapeHtml(customerName) +
                '<br/>' +
                'Total: ' +
                Money.currencyFormat(creditNote.total, creditNote.currency, $scope.company.moneyFormat) +
                '<br/>' +
                'Date: ' +
                $filter('formatCompanyDate')(creditNote.date) +
                '</p>'
            );
        }

        function _issue(creditNote) {
            $scope.issuing = true;

            CreditNote.edit(
                {
                    id: creditNote.id,
                },
                {
                    draft: false,
                },
                function (updatedCreditNote) {
                    $scope.issuing = false;
                    angular.extend(creditNote, updatedCreditNote);
                },
                function (result) {
                    $scope.issuing = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function _void(creditNote) {
            $scope.deleting = true;

            CreditNote.void(
                {
                    id: creditNote.id,
                },
                function (updatedCreditNote) {
                    $scope.deleting = false;
                    angular.extend(creditNote, updatedCreditNote);
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
                        return 'credit_note';
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
                    objectType: () => 'credit_note',
                    options: () => buildFindParams($scope.filter, []),
                    count: () => $scope.total_count,
                },
            });
        };

        //
        // Initialization
        //

        $scope.initializeListPage();

        Core.setTitle('Credit Notes');

        loadAutomations();

        function buildFindParams(input, columns) {
            let params = {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter(input, $scope._filterFields),
                sort: input.sort,
                include: $scope.tableHasMetadata(columns) ? 'customerName,metadata' : 'customerName',
            };

            if (input.status.value === 'open') {
                params.filter.paid = 0;
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
                $scope.columns = ColumnArrangementService.getSelectedColumns('credit_note', $scope.allColumns);
                resolve();
            });
        }

        function loadAutomations() {
            AutomationWorkflow.loadAutomations(
                'credit_note',
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
