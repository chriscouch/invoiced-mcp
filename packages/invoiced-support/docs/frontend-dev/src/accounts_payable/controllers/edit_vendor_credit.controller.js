/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('EditVendorCreditController', EditVendorCreditController);

    EditVendorCreditController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        'VendorCredit',
        'LeavePageWarning',
        'Core',
        'selectedCompany',
    ];

    function EditVendorCreditController(
        $scope,
        $state,
        $stateParams,
        VendorCredit,
        LeavePageWarning,
        Core,
        selectedCompany,
    ) {
        $scope.save = save;
        $scope.addLineItem = addLineItem;
        $scope.removeLineItem = removeLineItem;

        $scope.vendorCredit = {
            vendor: $stateParams.vendor || null,
            number: null,
            date: new Date(),
            currency: selectedCompany.currency,
            line_items: [],
            import_id: null,
        };
        $scope.vendorCreditTotal = 0;

        LeavePageWarning.watchForm($scope, 'modelForm');
        load();

        $scope.$watch(
            'vendorCredit',
            function () {
                $scope.vendorCreditTotal = 0;
                angular.forEach($scope.vendorCredit.line_items, function (lineItem) {
                    if (!isNaN(lineItem.amount)) {
                        $scope.vendorCreditTotal += lineItem.amount;
                    }
                });
            },
            true,
        );

        function load() {
            if (!$stateParams.id) {
                Core.setTitle('New Vendor Credit');
                addLineItem($scope.vendorCredit);

                return;
            }

            Core.setTitle('Edit Vendor Credit');
            $scope.loading = true;
            VendorCredit.find(
                { id: $stateParams.id },
                function (vendorCredit) {
                    $scope.vendorCredit = vendorCredit;
                    $scope.vendorCredit.date = moment($scope.vendorCredit.date).toDate();
                    $scope.loading = false;
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data.message;
                },
            );
        }

        function addLineItem(vendorCredit) {
            vendorCredit.line_items.push({
                description: '',
                amount: null,
            });
        }

        function removeLineItem(vendorCredit, $index) {
            vendorCredit.line_items.splice($index, 1);
        }

        function save(vendorCredit) {
            $scope.saving = true;

            let params = {
                vendor: parseInt(vendorCredit.vendor.id),
                number: vendorCredit.number,
                date: moment(vendorCredit.date).format('YYYY-MM-DD'),
                currency: vendorCredit.currency,
                import_id: vendorCredit.import_id,
                line_items: [],
            };

            angular.forEach(vendorCredit.line_items, function (lineItem) {
                params.line_items.push({
                    id: lineItem.id || null,
                    description: lineItem.description,
                    amount: lineItem.amount,
                });
            });

            if (vendorCredit.id) {
                VendorCredit.edit(
                    { id: vendorCredit.id },
                    params,
                    function () {
                        $scope.saving = false;
                        LeavePageWarning.unblock();
                        $state.go('manage.vendor_credit.view.summary', { id: vendorCredit.id });
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data.message;
                    },
                );
            } else {
                VendorCredit.create(
                    params,
                    function (vendorCredit2) {
                        $scope.saving = false;
                        LeavePageWarning.unblock();
                        $state.go('manage.vendor_credit.view.summary', { id: vendorCredit2.id });
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data.message;
                    },
                );
            }
        }
    }
})();
