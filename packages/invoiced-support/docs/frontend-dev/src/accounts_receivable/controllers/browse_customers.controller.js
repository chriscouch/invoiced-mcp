(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('BrowseCustomersController', BrowseCustomersController);

    BrowseCustomersController.$inject = [
        '$scope',
        '$stateParams',
        '$state',
        '$controller',
        '$modal',
        '$filter',
        '$q',
        '$translate',
        'LeavePageWarning',
        'Customer',
        'Core',
        'Metadata',
        'Settings',
        'ChasingCadence',
        'LateFeeSchedule',
        'Feature',
        'ColumnArrangementService',
        'UiFilterService',
        'AutomationWorkflow',
    ];

    function BrowseCustomersController(
        $scope,
        $stateParams,
        $state,
        $controller,
        $modal,
        $filter,
        $q,
        $translate,
        LeavePageWarning,
        Customer,
        Core,
        Metadata,
        Settings,
        ChasingCadence,
        LateFeeSchedule,
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

        $scope.model = Customer;
        $scope.modelTitleSingular = 'Customer';
        $scope.modelTitlePlural = 'Customers';

        //
        // Presets
        //

        $scope.customers = [];
        $scope.cadences = [];
        $scope.lateFeeSchedules = [];
        $scope.allColumns = ColumnArrangementService.getColumnsFromConfig('customer');

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

            $q.all([$scope.loadCustomFields('customer', true)]);
            return buildFindParams($scope.filter, $scope.columns);
        };

        $scope.postFindAll = function (customers) {
            angular.forEach(customers, function (customer) {
                customer.balance = 0;
                angular.forEach(customer.aging, function (age) {
                    customer.balance += age.amount;
                });
            });

            $scope.customers = customers;
        };

        $scope.filterFields = function () {
            let fields = [
                {
                    id: 'active',
                    label: 'Active',
                    type: 'boolean',
                    defaultValue: '1',
                    displayInFilterString: function (filter) {
                        return filter.active !== '1';
                    },
                },
                {
                    id: 'address1',
                    label: $translate.instant('address.address1'),
                    type: 'string',
                },
                {
                    id: 'address2',
                    label: $translate.instant('address.address2'),
                    type: 'string',
                },
                {
                    id: 'attention_to',
                    label: 'Attention To',
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
                    id: 'autopay',
                    label: 'AutoPay',
                    type: 'boolean',
                },
                {
                    id: 'autopay_delay_days',
                    label: 'AutoPay Delay Days',
                    type: 'number',
                },
                {
                    id: 'avalara_entity_use_code',
                    label: 'Avalara Entity Use Code',
                    type: 'string',
                },
                {
                    id: 'avalara_exemption_number',
                    label: 'Avalara Exemption Number',
                    type: 'string',
                },
                {
                    id: 'bill_to_parent',
                    label: 'Bill To Parent',
                    type: 'boolean',
                },
                {
                    id: 'city',
                    label: $translate.instant('address.city'),
                    type: 'string',
                },
                {
                    id: 'consolidated',
                    label: 'Consolidated',
                    type: 'boolean',
                },
                {
                    id: 'convenience_fee',
                    label: 'Convenience Fees',
                    type: 'boolean',
                },
                {
                    id: 'country',
                    label: $translate.instant('address.country'),
                    type: 'enum',
                    values: UiFilterService.getCountryChoices(),
                },
                {
                    id: 'created_at',
                    label: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'credit_balance',
                    label: 'Credit Balance',
                    type: 'money',
                },
                {
                    id: 'credit_hold',
                    label: 'Credit Hold',
                    type: 'boolean',
                },
                {
                    id: 'credit_limit',
                    label: 'Credit Limit',
                    type: 'money',
                },
                {
                    id: 'currency',
                    label: 'Currency',
                    type: 'enum',
                    values: UiFilterService.getCurrencyChoices(),
                },
                {
                    id: 'email',
                    label: 'Email Address',
                    type: 'string',
                },
                {
                    id: 'language',
                    label: 'Language',
                    type: 'string',
                },
                {
                    id: 'late_fee_schedule',
                    label: 'Late Fee Schedule',
                    type: 'enum',
                    values: $scope.lateFeeSchedules,
                },
                {
                    id: 'name',
                    label: 'Name',
                    type: 'string',
                },
                {
                    id: 'number',
                    label: 'Customer #',
                    type: 'string',
                },
                {
                    id: 'owner',
                    label: 'Owner',
                    type: 'user',
                },
                {
                    id: 'parent_customer',
                    label: 'Parent Customer',
                    type: 'customer',
                },
                {
                    id: 'payment_terms',
                    label: 'Payment Terms',
                    type: 'string',
                },
                {
                    id: 'phone',
                    label: 'Phone',
                    type: 'string',
                },
                {
                    id: 'postal_code',
                    label: $translate.instant('address.postal_code'),
                    type: 'string',
                },
                {
                    id: 'state',
                    label: $translate.instant('address.state'),
                    type: 'string',
                },
                {
                    id: 'tax_id',
                    label: 'Tax ID',
                    type: 'string',
                },
                {
                    id: 'taxable',
                    label: 'Taxable',
                    type: 'boolean',
                },
                {
                    id: 'type',
                    label: 'Entity Type',
                    type: 'enum',
                    values: [
                        {
                            value: 'company',
                            text: 'Company',
                        },
                        {
                            value: 'government',
                            text: 'Government',
                        },
                        {
                            value: 'non_profit',
                            text: 'Non-Profit',
                        },
                        {
                            value: 'person',
                            text: 'Individual',
                        },
                    ],
                },
                {
                    id: 'updated_at',
                    label: 'Updated At',
                    type: 'datetime',
                },
                {
                    id: 'hasBalance',
                    label: 'Open Balance',
                    serialize: false,
                    type: 'enum',
                    values: [
                        {
                            text: 'No Balance',
                            value: '0',
                        },
                        {
                            text: 'Has Balance',
                            value: '1',
                        },
                    ],
                },
                {
                    id: 'default_source_type',
                    label: 'Default Payment Method',
                    type: 'enum',
                    values: [
                        { value: 'bank_account', text: 'Bank Account' },
                        { value: 'card', text: 'Card' },
                    ],
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'name ASC',
                    values: [
                        { value: 'name ASC', text: 'Name, Ascending Order' },
                        { value: 'name DESC', text: 'Name, Descending Order' },
                        { value: 'number ASC', text: 'Account #, Ascending Order' },
                        { value: 'number DESC', text: 'Account #, Descending Order' },
                        { value: 'created_at ASC', text: 'Created Date, Oldest First' },
                        { value: 'created_at DESC', text: 'Created Date, Newest First' },
                    ],
                },
            ];

            if (Feature.hasFeature('smart_chasing')) {
                fields.push({
                    id: 'chase',
                    label: 'Chase',
                    type: 'boolean',
                });
                fields.push({
                    id: 'chasing_cadence',
                    label: 'Chasing Cadence',
                    type: 'enum',
                    values: $scope.cadences,
                });
            }

            return fields.concat(UiFilterService.buildCustomFieldFilters($scope.customFields));
        };

        $scope.noResults = function () {
            return $scope.customers.length === 0;
        };

        /* Customers */

        $scope.customerModal = function (customer) {
            LeavePageWarning.block();

            customer = customer || false;

            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/customers/edit-customer.html',
                controller: 'EditCustomerController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    model: function () {
                        return customer;
                    },
                },
            });

            modalInstance.result.then(
                function (c) {
                    LeavePageWarning.unblock();

                    if (customer.id) {
                        Core.flashMessage('Customer profile for ' + c.name + ' was updated', 'success');
                        angular.extend(customer, c);
                    } else {
                        Core.flashMessage('A customer profile for ' + c.name + ' was created', 'success');
                        $state.go('manage.customer.view.summary', {
                            id: c.id,
                        });
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.deleteMessage = function (customer) {
            let escapeHtml = $filter('escapeHtml');

            return (
                '<p>Are you sure you want to delete this customer?</p>' +
                '<p><strong>' +
                escapeHtml(customer.name) +
                ' <small>' +
                escapeHtml(customer.number) +
                '</small></strong></p>' +
                "<p class='text-danger'>Any associated estimates, invoices, subscriptions, and payments will be permanently deleted.</p>"
            );
        };

        $scope.export = function (type) {
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
                        return type;
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
                    objectType: () => 'customer',
                    options: () => buildFindParams($scope.filter, []),
                    count: () => $scope.total_count,
                },
            });
        };

        /* Active status */
        $scope.setActiveStatus = function (customer, active) {
            Customer.edit(
                {
                    id: customer.id,
                },
                {
                    active: active,
                },
                function () {
                    customer.active = active;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        /* Statements */

        $scope.generateStatement = function (customer) {
            $modal.open({
                templateUrl: 'accounts_receivable/views/customers/generate-statement.html',
                controller: 'GenerateStatementController',
                windowClass: 'generate-statement-modal',
                resolve: {
                    customer: function () {
                        return customer;
                    },
                },
            });
        };

        //
        // Initialization
        //
        $scope.initializeListPage();

        loadLateFeeSchedules();
        loadChasingCadences();
        loadAutomations();

        Core.setTitle('Customers');

        function loadSettings() {
            return $q(function (resolve) {
                $scope.columns = ColumnArrangementService.getSelectedColumns('customer', $scope.allColumns);

                Settings.accountsReceivable(function (settings) {
                    const agingIndex = $scope.columns.findIndex(function (item) {
                        return item.id === 'aging';
                    });
                    if (agingIndex === -1) {
                        resolve();
                        return;
                    }

                    // Replace the `aging` column with the actual aging buckets
                    let aging = [];
                    for (let i in settings.aging_buckets) {
                        if (!settings.aging_buckets.hasOwnProperty(i)) {
                            continue;
                        }
                        const lower = settings.aging_buckets[i];
                        let agingTitle = lower + '+ Days';
                        if (i < settings.aging_buckets.length - 1) {
                            if (lower == -1) {
                                agingTitle = 'Current';
                            } else {
                                let upper = settings.aging_buckets[parseInt(i) + 1] - 1;
                                agingTitle = lower + ' - ' + upper + ' Days';
                            }
                        }
                        aging.push({
                            id: 'aging_bucket',
                            name: agingTitle,
                            type: 'money',
                            internalIndex: i,
                            sortable: false,
                        });
                    }

                    $scope.columns.splice(agingIndex, 1, ...aging);

                    resolve();
                });
            });
        }

        function loadLateFeeSchedules() {
            LateFeeSchedule.findAll(
                {
                    paginate: 'none',
                },
                function (lateFeeSchedules) {
                    $scope.lateFeeSchedules = [];
                    angular.forEach(lateFeeSchedules, function (lateFeeSchedule) {
                        // needed for ngOptions
                        lateFeeSchedule.id = '' + lateFeeSchedule.id;
                        $scope.lateFeeSchedules.push({ text: lateFeeSchedule.name, value: lateFeeSchedule.id });
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

        function loadChasingCadences() {
            if (!Feature.hasFeature('smart_chasing')) {
                return;
            }

            ChasingCadence.findAll(
                {
                    exclude: 'steps',
                    paginate: 'none',
                },
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
                'customer',
                automations => {
                    $scope.automations = automations;
                    $scope.generateFilterFields();
                    $scope.updateFilterString();
                },
                () => {},
            );
        }

        function buildFindParams(input, columns) {
            let loadMetadata = $scope.tableHasMetadata(columns);
            let include = 'aging';
            if (loadMetadata !== undefined) {
                include = 'aging,metadata';
                $scope.metadataLoaded = true;
            }
            let params = {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter(input, $scope._filterFields),
                sort: input.sort,
                include: include,
                expand: 'chasing_cadence,next_chase_step',
            };

            if (input.hasBalance.value === '1') {
                params.open_balance = true;
            } else if (input.hasBalance.value === '0') {
                params.open_balance = false;
            }

            if (input.automation.value) {
                params.automation = input.automation.value;
            }

            return params;
        }
    }
})();
