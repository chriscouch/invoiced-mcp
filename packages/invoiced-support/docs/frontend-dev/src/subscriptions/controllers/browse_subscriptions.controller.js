(function () {
    'use strict';

    angular.module('app.subscriptions').controller('BrowseSubscriptionsController', BrowseSubscriptionsController);

    BrowseSubscriptionsController.$inject = [
        '$scope',
        '$state',
        '$controller',
        '$modal',
        '$q',
        '$translate',
        'Subscription',
        'LeavePageWarning',
        'Core',
        'CustomField',
        'selectedCompany',
        'Feature',
        'ColumnArrangementService',
        'Metadata',
        'UiFilterService',
        'AutomationWorkflow',
    ];

    function BrowseSubscriptionsController(
        $scope,
        $state,
        $controller,
        $modal,
        $q,
        $translate,
        Subscription,
        LeavePageWarning,
        Core,
        CustomField,
        selectedCompany,
        Feature,
        ColumnArrangementService,
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

        $scope.model = Subscription;
        $scope.modelTitleSingular = 'Subscription';
        $scope.modelTitlePlural = 'Subscriptions';

        //
        // Presets
        //

        $scope.subscriptions = [];
        $scope.hasFeature = Feature.hasFeature('subscription_billing');
        $scope.allColumns = ColumnArrangementService.getColumnsFromConfig('subscription');

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

            $q.all([$scope.loadCustomFields('subscription', true)]);
            return buildFindParams($scope.filter, $scope.columns);
        };

        $scope.postFindAll = function (subscriptions) {
            $scope.subscriptions = subscriptions;
        };

        $scope.filterFields = function () {
            let fields = [
                {
                    id: 'automation',
                    label: 'Automation',
                    type: 'enum',
                    values: $scope.automations,
                    serialize: false,
                },
                {
                    id: 'bill_in',
                    label: 'Bill In',
                    type: 'enum',
                    values: [
                        {
                            value: 'advance',
                            text: 'Advance',
                        },
                        {
                            value: 'arrears',
                            text: 'Arrears',
                        },
                    ],
                },
                {
                    id: 'bill_in_advance_days',
                    label: 'Bill In Advance Days',
                    type: 'number',
                },
                {
                    id: 'cancel_at_period_end',
                    label: 'Cancel At Period End',
                    type: 'boolean',
                },
                {
                    id: 'canceled',
                    label: 'Canceled',
                    type: 'boolean',
                },
                {
                    id: 'canceled_at',
                    label: 'Canceled At',
                    type: 'datetime',
                },
                {
                    id: 'canceled_reason',
                    label: 'Canceled Reason',
                    type: 'string',
                },
                {
                    id: 'contract_period_end',
                    label: 'Contract Period End',
                    type: 'date',
                },
                {
                    id: 'contract_period_start',
                    label: 'Contract Period Start',
                    type: 'date',
                },
                {
                    id: 'contract_renewal_cycles',
                    label: 'Contract Renewal Cycles',
                    type: 'number',
                },
                {
                    id: 'contract_renewal_mode',
                    label: 'Contract Renewal Mode',
                    type: 'enum',
                    values: [
                        {
                            value: 'none',
                            text: 'None',
                        },
                        {
                            value: 'renew_once',
                            text: 'Renew Once',
                        },
                        {
                            value: 'manual',
                            text: 'Manual',
                        },
                        {
                            value: 'auto',
                            text: 'Auto',
                        },
                    ],
                },
                {
                    id: 'created_at',
                    label: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'customer',
                    label: 'Customer',
                    type: 'customer',
                },
                {
                    id: 'cycles',
                    label: 'Cycles',
                    type: 'number',
                },
                {
                    id: 'finished',
                    label: 'Finished',
                    type: 'boolean',
                },
                {
                    id: 'mrr',
                    label: 'MRR',
                    type: 'money',
                },
                {
                    id: 'paused',
                    label: 'Paused',
                    type: 'boolean',
                },
                {
                    id: 'pending_renewal',
                    label: 'Pending Renewal',
                    type: 'boolean',
                },
                {
                    id: 'period_end',
                    label: 'Period End',
                    type: 'date',
                },
                {
                    id: 'period_start',
                    label: 'Period Start',
                    type: 'date',
                },
                {
                    id: 'quantity',
                    label: 'Quantity',
                    type: 'number',
                },
                {
                    id: 'recurring_total',
                    label: 'Recurring Total',
                    type: 'money',
                },
                {
                    id: 'renewed_last',
                    label: 'Last Bill Date',
                    type: 'date',
                },
                {
                    id: 'renews_next',
                    label: 'Next Bill Date',
                    type: 'date',
                },
                {
                    id: 'snap_to_nth_day',
                    label: 'Billing Day',
                    type: 'number',
                },
                {
                    id: 'start_date',
                    label: 'Start Date',
                    type: 'date',
                },
                {
                    id: 'updated_at',
                    label: 'Updated At',
                    type: 'datetime',
                },
                {
                    id: 'status',
                    label: 'Status',
                    serialize: false,
                    type: 'enum',
                    values: [
                        { value: '', text: $translate.instant('subscriptions.statuses.running') },
                        { value: 'not_started', text: $translate.instant('subscriptions.statuses.trialing') },
                        { value: 'active', text: $translate.instant('general.active') },
                        { value: 'past_due', text: $translate.instant('subscriptions.statuses.past_due') },
                        {
                            value: 'pending_renewal',
                            text: $translate.instant('subscriptions.statuses.pending_renewal'),
                        },
                        { value: 'paused', text: $translate.instant('subscriptions.statuses.paused') },
                        { value: 'finished', text: $translate.instant('subscriptions.statuses.finished') },
                        { value: 'canceled', text: $translate.instant('subscriptions.statuses.canceled') },
                    ],
                },
                {
                    id: 'contract',
                    label: 'Contract',
                    serialize: false,
                    type: 'enum',
                    values: [
                        { value: '0', text: 'No Contract' },
                        { value: '1', text: 'Has Contract' },
                    ],
                },
                {
                    id: 'plan',
                    label: 'Plan',
                    serialize: false,
                    type: 'plan',
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'renews_next ASC',
                    values: [
                        { value: 'Customers.name ASC', text: 'Customer, Ascending Order' },
                        { value: 'Customers.name DESC', text: 'Customer, Descending Order' },
                        { value: 'plan ASC', text: 'Plan ID, Ascending Order' },
                        { value: 'plan DESC', text: 'Plan ID, Descending Order' },
                        { value: 'recurring_total ASC', text: 'Recurring Amount, Ascending Order' },
                        { value: 'recurring_total DESC', text: 'Recurring Amount, Descending Order' },
                        { value: 'mrr ASC', text: 'MRR, Ascending Order' },
                        { value: 'mrr DESC', text: 'MRR, Descending Order' },
                        { value: 'renews_next ASC', text: 'Next Bill Date, Ascending Order' },
                        { value: 'renews_next DESC', text: 'Next Bill Date, Descending Order' },
                        { value: 'contract_period_end ASC', text: 'Contract End Date, Ascending Order' },
                        { value: 'contract_period_end DESC', text: 'Contract End Date, Descending Order' },
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
            return $scope.subscriptions.length === 0;
        };

        $scope.addModal = function () {
            LeavePageWarning.block();

            // TODO for this to work, must also include rates and addons in api response

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/new-subscription.html',
                controller: 'NewSubscriptionController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    customer: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function (subscription) {
                    LeavePageWarning.unblock();

                    Core.flashMessage('Your subscription has been created', 'success');

                    $state.go('manage.subscription.view.summary', {
                        id: subscription.id,
                    });
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.editModal = function (subscription) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/edit-subscription.html',
                controller: 'EditSubscriptionController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    LeavePageWarning.unblock();
                    angular.extend(subscription, result);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.cancelModal = function (subscription) {
            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/cancel-subscription.html',
                controller: 'CancelSubscriptionController',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
            });

            modalInstance.result.then(
                function (updated) {
                    angular.extend(subscription, updated);

                    if (subscription.status === 'canceled') {
                        Core.flashMessage('Your subscription has been canceled', 'success');
                        $scope.postDelete(subscription);
                    } else {
                        Core.flashMessage('Your subscription has been updated', 'success');
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.renewContract = function (subscription) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/renew-contract.html',
                controller: 'RenewContractController',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
                size: 'sm',
            });

            modalInstance.result.then(
                function (updatedSubscription) {
                    LeavePageWarning.unblock();
                    Core.flashMessage('Your contract has been renewed', 'success');
                    angular.extend(subscription, updatedSubscription);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

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
                        return 'subscription';
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
                    objectType: function () {
                        return 'subscription';
                    },
                    options: function () {
                        const options = buildFindParams($scope.filter, []);
                        return {
                            advanced_filter: options.advanced_filter,
                            filter: options.filter,
                        };
                    },
                    count: () => $scope.total_count,
                },
            });
        };

        //
        // Initialization
        //

        $scope.initializeListPage();
        Core.setTitle('Subscriptions');
        loadAutomations();

        function buildFindParams(input, columns) {
            let params = {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter(input, $scope._filterFields),
                sort: input.sort,
                expand: 'plan,customer',
                include: $scope.tableHasMetadata(columns) ? 'metadata' : '',
            };

            if (input.status.value !== '') {
                if (input.status.value === 'canceled') {
                    params.canceled = true;
                } else if (input.status.value === 'finished') {
                    params.finished = true;
                } else {
                    params.filter.status = input.status.value;
                }
            }

            if (input.contract && input.contract.value) {
                params.contract = input.contract.value;
            }

            if (input.plan && input.plan.value) {
                params.plan = typeof input.plan.value.id !== 'undefined' ? input.plan.value.id : input.plan.value;
            }

            if (input.automation.value) {
                params.automation = input.automation.value;
            }

            return params;
        }

        function loadSettings() {
            return $q(function (resolve) {
                $scope.columns = ColumnArrangementService.getSelectedColumns('subscription', $scope.allColumns);
                resolve();
            });
        }

        function loadAutomations() {
            AutomationWorkflow.loadAutomations(
                'subscription',
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
