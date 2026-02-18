(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('BrowseVendorPaymentsController', BrowseVendorPaymentsController);

    BrowseVendorPaymentsController.$inject = [
        '$scope',
        '$controller',
        'Core',
        'VendorPayment',
        'Vendor',
        'UiFilterService',
    ];

    function BrowseVendorPaymentsController($scope, $controller, Core, VendorPayment, Vendor, UiFilterService) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = VendorPayment;
        $scope.modelTitleSingular = 'Payment';
        $scope.modelTitlePlural = 'Payments';

        $scope.payments = [];
        $scope.vendors = [];

        $scope.noResults = noResults;
        $scope.preFindAll = preFindAll;
        $scope.postFindAll = postFindAll;
        $scope.filterFields = filterFields;

        $scope.deleteMessage = deleteMessage;
        $scope.postDelete = postDelete;

        //
        // Initialization
        //

        $scope.initializeListPage();
        Core.setTitle('Vendor Payments');
        loadVendors();

        function preFindAll() {
            return {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter($scope.filter, $scope._filterFields),
                expand: 'vendor',
                sort: $scope.filter.sort,
            };
        }

        function postFindAll(payments) {
            $scope.payments = payments;
        }

        function filterFields() {
            return [
                {
                    id: 'amount',
                    label: 'Amount',
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
                    id: 'expected_arrival_date',
                    label: 'Expected Arrival Date',
                    type: 'date',
                },
                {
                    id: 'notes',
                    label: 'Notes',
                    type: 'string',
                },
                {
                    id: 'number',
                    label: 'Payment #',
                    type: 'string',
                },
                {
                    id: 'vendor_payment_batch',
                    label: 'Payment Batch ID',
                    type: 'string',
                },
                {
                    id: 'payment_method',
                    label: 'Payment Method',
                    type: 'string',
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
                    id: 'voided',
                    label: 'Voided',
                    type: 'boolean',
                },
                {
                    id: 'vendor',
                    label: 'Vendor',
                    type: 'enum',
                    values: $scope.vendors,
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'date DESC',
                    values: [
                        { value: 'date ASC', text: 'Date, Oldest First' },
                        { value: 'date DESC', text: 'Date, Newest First' },
                        { value: 'amount ASC', text: 'Amount, Lowest First' },
                        { value: 'amount DESC', text: 'Amount, Highest First' },
                    ],
                },
            ];
        }

        function noResults() {
            return $scope.payments.length === 0;
        }

        function deleteMessage() {
            return 'Are you sure you want to void this payment? This operation is irreversible.';
        }

        function postDelete(model) {
            model.voided = true;
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
    }
})();
