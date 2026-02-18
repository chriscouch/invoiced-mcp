(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('BrowseBillsController', BrowseBillsController);

    BrowseBillsController.$inject = [
        '$scope',
        '$state',
        '$modal',
        '$controller',
        'Core',
        'Bill',
        'NetworkDocument',
        'ApprovalWorkflow',
        'Vendor',
        'Feature',
        'UiFilterService',
    ];

    function BrowseBillsController(
        $scope,
        $state,
        $modal,
        $controller,
        Core,
        Bill,
        NetworkDocument,
        ApprovalWorkflow,
        Vendor,
        Feature,
        UiFilterService,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Bill;
        $scope.modelTitleSingular = 'Bill';
        $scope.modelTitlePlural = 'Bills';

        $scope.bills = [];
        $scope.approvalWorkflows = [];
        $scope.vendors = [];

        $scope.noResults = noResults;
        $scope.preFindAll = preFindAll;
        $scope.postFindAll = postFindAll;
        $scope.filterFields = filterFields;

        $scope.download = download;

        //
        // Initialization
        //

        $scope.initializeListPage();
        Core.setTitle('Bills');
        loadVendors();
        loadApprovalWorkflows();

        function preFindAll() {
            return {
                expand: 'vendor',
                filter: {},
                advanced_filter: UiFilterService.serializeFilter($scope.filter, $scope._filterFields),
                sort: $scope.filter.sort,
            };
        }

        function postFindAll(bills) {
            $scope.bills = bills;
        }

        function filterFields() {
            return [
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
                    id: 'due_date',
                    label: 'Due Date',
                    type: 'date',
                },
                {
                    id: 'number',
                    label: 'Number',
                    type: 'string',
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
                    id: 'voided',
                    label: 'Voided',
                    type: 'boolean',
                },
                {
                    id: 'source',
                    label: 'Source',
                    type: 'enum',
                    values: [
                        { value: 'Network', text: 'Network' },
                        { value: 'Keyed', text: 'Keyed' },
                        { value: 'Imported', text: 'Imported' },
                    ],
                },
                {
                    id: 'status',
                    label: 'Status',
                    type: 'enum',
                    values: [
                        { value: 'PendingApproval', text: 'Pending Approval' },
                        { value: 'Approved', text: 'Approved' },
                        { value: 'Rejected', text: 'Rejected' },
                        { value: 'Paid', text: 'Paid' },
                        { value: 'Voided', text: 'Voided' },
                    ],
                    displayInFilterString: function (filter) {
                        return (
                            filter.status &&
                            filter.status.value !== 'PendingApproval' &&
                            filter.status.value !== 'Approved' &&
                            filter.status.value !== 'Paid'
                        );
                    },
                },
                {
                    id: 'vendor',
                    label: 'Vendor',
                    type: 'enum',
                    values: $scope.vendors,
                },
                {
                    id: 'approval_workflow',
                    label: 'Approval Workflow',
                    type: 'enum',
                    values: $scope.approvalWorkflows,
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'date DESC',
                    values: [
                        { value: 'date ASC', text: 'Date, Oldest First' },
                        { value: 'date DESC', text: 'Date, Newest First' },
                        { value: 'due_date ASC', text: 'Due Date, Oldest First' },
                        { value: 'due_date DESC', text: 'Due Date, Newest First' },
                        { value: 'total ASC', text: 'Total, Lowest First' },
                        { value: 'total DESC', text: 'Total, Highest First' },
                    ],
                },
            ];
        }

        function noResults() {
            return $scope.bills.length === 0;
        }

        function download(doc) {
            $scope.downloading = true;
            NetworkDocument.download(
                doc.network_document,
                true,
                function (data, filename) {
                    $scope.downloading = false;
                    Core.createAndDownloadBlobFile(data, filename);
                },
                function (error) {
                    $scope.downloading = false;
                    Core.showMessage(error.message, 'error');
                },
            );
        }

        function loadVendors() {
            // TODO: does not load full vendor list. Should create a select vendor component instead
            Vendor.findAll(
                {
                    paginate: 'none',
                },
                function (vendors) {
                    $scope.vendors = [];
                    angular.forEach(vendors, function (vendor) {
                        // needed for ngOptions
                        vendor.id = '' + vendor.id;
                        $scope.vendors.push({
                            text: vendor.name + (!vendor.active ? ' (inactive)' : ''),
                            value: vendor.id,
                        });
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

        function loadApprovalWorkflows() {
            if (!Feature.hasFeature('approval_workflow')) {
                return;
            }

            ApprovalWorkflow.findAll(
                {
                    paginate: 'none',
                },
                function (workflows) {
                    $scope.approvalWorkflows = [];
                    angular.forEach(workflows, function (workflow) {
                        // needed for ngOptions
                        workflow.id = '' + workflow.id;
                        $scope.approvalWorkflows.push({ text: workflow.name, value: workflow.id });
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
    }
})();
