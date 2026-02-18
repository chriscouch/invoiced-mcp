/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('BrowseVendorsController', BrowseVendorsController);

    BrowseVendorsController.$inject = [
        '$scope',
        '$state',
        '$modal',
        '$controller',
        'Core',
        'LeavePageWarning',
        'Vendor',
        'Network',
        'UiFilterService',
        'AutomationWorkflow',
    ];

    function BrowseVendorsController(
        $scope,
        $state,
        $modal,
        $controller,
        Core,
        LeavePageWarning,
        Vendor,
        Network,
        UiFilterService,
        AutomationWorkflow,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Vendor;
        $scope.modelTitleSingular = 'Vendor';
        $scope.modelTitlePlural = 'Vendors';

        $scope.vendors = [];

        $scope.noResults = noResults;
        $scope.preFindAll = preFindAll;
        $scope.postFindAll = postFindAll;
        $scope.filterFields = filterFields;

        $scope.create = create;
        $scope.delete = deleteConnection;

        //
        // Initialization
        //

        $scope.initializeListPage();
        Core.setTitle('Vendors');
        loadAutomations();

        $scope.automate = function () {
            $modal.open({
                templateUrl: 'automations/views/automate-mass-object.html',
                controller: 'AutomateMassObjectController',
                resolve: {
                    objectType: () => 'vendor',
                    options: () => preFindAll(),
                    count: () => $scope.total_count,
                },
            });
        };

        function create() {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/vendors/edit.html',
                controller: 'EditVendorController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    model: function () {
                        return null;
                    },
                },
            });

            modalInstance.result.then(
                function (vendor) {
                    LeavePageWarning.unblock();

                    Core.flashMessage('A vendor profile for ' + vendor.name + ' was created', 'success');

                    $state.go('manage.vendor.view.summary', {
                        id: vendor.id,
                    });
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        }

        function deleteConnection(vendor) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this connection?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting = true;

                        Network.deleteConnection(
                            {
                                id: vendor.network_connection,
                                type: 'vendors',
                            },
                            function () {
                                $scope.deleting = false;
                                vendor.active = false;
                            },
                            function (result) {
                                $scope.deleting = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        }

        function preFindAll() {
            const params = {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter($scope.filter, $scope._filterFields),
                include: 'balance',
                sort: $scope.filter.sort,
            };
            if ($scope.filter.automation.value) {
                params.automation = $scope.filter.automation.value;
            }
            return params;
        }

        function postFindAll(vendors) {
            $scope.vendors = vendors;
        }

        function filterFields() {
            return [
                {
                    id: 'automation',
                    label: 'Automation',
                    type: 'enum',
                    values: $scope.automations,
                    serialize: false,
                },
                {
                    id: 'created_at',
                    label: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'name',
                    label: 'Name',
                    type: 'string',
                },
                {
                    id: 'number',
                    label: 'Number',
                    type: 'string',
                },
                {
                    id: 'updated_at',
                    label: 'Updated At',
                    type: 'datetime',
                },
                {
                    id: 'active',
                    label: 'Active',
                    type: 'boolean',
                    displayInFilterString: function (filter) {
                        return filter.active !== '1';
                    },
                    defaultValue: '1',
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'name ASC',
                    values: [
                        { value: 'name ASC', text: 'Name, Ascending Order' },
                        { value: 'name DESC', text: 'Name, Descending Order' },
                        { value: 'number ASC', text: 'Vendor #, Ascending Order' },
                        { value: 'number DESC', text: 'Vendor #, Descending Order' },
                        { value: 'created_at ASC', text: 'Created Date, Oldest First' },
                        { value: 'created_at DESC', text: 'Created Date, Newest First' },
                    ],
                },
            ];
        }

        function noResults() {
            return $scope.vendors.length === 0;
        }

        function loadAutomations() {
            AutomationWorkflow.loadAutomations(
                'vendor',
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
