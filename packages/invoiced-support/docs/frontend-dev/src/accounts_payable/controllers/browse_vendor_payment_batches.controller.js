(function () {
    'use strict';

    angular
        .module('app.accounts_payable')
        .controller('BrowseVendorPaymentBatchesController', BrowseVendorPaymentBatchesController);

    BrowseVendorPaymentBatchesController.$inject = [
        '$scope',
        '$modal',
        '$controller',
        '$q',
        '$state',
        'Core',
        'VendorPaymentBatch',
        'selectedCompany',
        'UiFilterService',
    ];

    function BrowseVendorPaymentBatchesController(
        $scope,
        $modal,
        $controller,
        $q,
        $state,
        Core,
        VendorPaymentBatch,
        selectedCompany,
        UiFilterService,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = VendorPaymentBatch;
        $scope.modelTitleSingular = 'Payment Batch';
        $scope.modelTitlePlural = 'Payment Batches';

        $scope.paymentBatches = [];

        $scope.noResults = noResults;
        $scope.preFindAll = preFindAll;
        $scope.postFindAll = postFindAll;
        $scope.filterFields = advancedFilterFields;

        //
        // Initialization
        //

        $scope.initializeListPage();
        Core.setTitle('Payment Batches');

        function preFindAll() {
            return {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter($scope.filter, $scope._filterFields),
                expand: 'vendor',
                sort: $scope.filter.sort,
            };
        }

        function postFindAll(payments) {
            $scope.paymentBatches = payments;
        }

        function noResults() {
            return $scope.paymentBatches.length === 0;
        }

        function advancedFilterFields() {
            return [
                {
                    id: 'check_layout',
                    label: 'Check Layout',
                    type: 'enum',
                    values: [
                        {
                            value: 1,
                            text: 'CheckOnTop',
                        },
                        {
                            value: 2,
                            text: 'ThreePerPage',
                        },
                    ],
                },
                {
                    id: 'created_at',
                    label: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'currency',
                    label: 'Currency',
                    type: 'currency',
                },
                {
                    id: 'member',
                    label: 'Created By',
                    type: 'user',
                },
                {
                    id: 'name',
                    label: 'Name',
                    type: 'string',
                },
                {
                    id: 'number',
                    label: 'Batch #',
                    type: 'string',
                },
                {
                    id: 'payment_method',
                    label: 'Payment Method',
                    type: 'string',
                },
                {
                    id: 'status',
                    label: 'Status',
                    type: 'enum',
                    values: [
                        {
                            value: 1,
                            text: 'Created',
                        },
                        {
                            value: 2,
                            text: 'Processing',
                        },
                        {
                            value: 3,
                            text: 'Queued',
                        },
                        {
                            value: 4,
                            text: 'Finished',
                        },
                        {
                            value: 5,
                            text: 'Voided',
                        },
                    ],
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
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'created_at DESC',
                    values: [
                        { value: 'created_at ASC', text: 'Created At, Oldest First' },
                        { value: 'created_at DESC', text: 'Created At, Newest First' },
                    ],
                },
            ];
        }
    }
})();
