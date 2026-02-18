/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('EditBillController', EditBillController);

    EditBillController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        'Bill',
        'LeavePageWarning',
        'Core',
        'selectedCompany',
    ];

    function EditBillController($scope, $state, $stateParams, Bill, LeavePageWarning, Core, selectedCompany) {
        $scope.save = save;
        $scope.addLineItem = addLineItem;
        $scope.removeLineItem = removeLineItem;
        $scope.bill = {
            vendor: $stateParams.vendor || null,
            number: null,
            date: new Date(),
            due_date: null,
            currency: selectedCompany.currency,
            line_items: [],
            import_id: null,
        };
        $scope.billTotal = 0;

        LeavePageWarning.watchForm($scope, 'modelForm');
        load();

        $scope.$watch(
            'bill',
            function () {
                $scope.billTotal = 0;
                angular.forEach($scope.bill.line_items, function (lineItem) {
                    if (!isNaN(lineItem.amount)) {
                        $scope.billTotal += lineItem.amount;
                    }
                });
            },
            true,
        );

        function load() {
            if (!$stateParams.id) {
                Core.setTitle('New Bill');
                addLineItem($scope.bill);

                return;
            }

            Core.setTitle('Edit Bill');
            $scope.loading = true;
            Bill.find(
                { id: $stateParams.id },
                function (bill) {
                    $scope.bill = bill;
                    $scope.bill.date = moment($scope.bill.date).toDate();
                    $scope.bill.due_date = $scope.bill.due_date ? moment($scope.bill.due_date).toDate() : null;
                    $scope.loading = false;
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data.message;
                },
            );
        }

        function addLineItem(bill) {
            bill.line_items.push({
                description: '',
                amount: null,
            });
        }

        function removeLineItem(bill, $index) {
            bill.line_items.splice($index, 1);
        }

        function save(bill) {
            $scope.saving = true;

            let params = {
                vendor: parseInt(bill.vendor.id),
                number: bill.number,
                date: moment(bill.date).format('YYYY-MM-DD'),
                due_date: bill.due_date ? moment(bill.due_date).format('YYYY-MM-DD') : null,
                currency: bill.currency,
                import_id: bill.import_id,
                line_items: [],
            };

            angular.forEach(bill.line_items, function (lineItem) {
                params.line_items.push({
                    id: lineItem.id || null,
                    description: lineItem.description,
                    amount: lineItem.amount,
                });
            });

            if (bill.id) {
                Bill.edit(
                    { id: bill.id },
                    params,
                    function () {
                        $scope.saving = false;
                        LeavePageWarning.unblock();
                        $state.go('manage.bill.view.summary', { id: bill.id });
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data.message;
                    },
                );
            } else {
                Bill.create(
                    params,
                    function (bill2) {
                        $scope.saving = false;
                        LeavePageWarning.unblock();
                        $state.go('manage.bill.view.summary', { id: bill2.id });
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
