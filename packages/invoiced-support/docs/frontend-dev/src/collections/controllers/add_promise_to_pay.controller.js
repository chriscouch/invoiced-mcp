/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('AddPromiseToPayController', AddPromiseToPayController);

    AddPromiseToPayController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'Invoice',
        'customer',
        'DatePickerService',
    ];

    function AddPromiseToPayController($scope, $modalInstance, selectedCompany, Invoice, customer, DatePickerService) {
        $scope.customer = customer;
        $scope.company = selectedCompany;

        $scope.expectedPaymentDate = {
            date: null,
            method: 'other',
        };
        $scope.selectedInvoices = {};

        $scope.dateOptions = DatePickerService.getOptions({
            minDate: 0,
            maxDate: '+3M',
        });

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
        };

        $scope.noSelectedInvoices = function () {
            for (let i in $scope.selectedInvoices) {
                if ($scope.selectedInvoices[i]) {
                    return false;
                }
            }

            return true;
        };

        $scope.atLeastOneSelectedInvoice = function () {
            for (let i in $scope.selectedInvoices) {
                if ($scope.selectedInvoices[i]) {
                    return true;
                }
            }

            return false;
        };

        $scope.checkAll = function () {
            for (let i in $scope.openInvoices) {
                $scope.selectedInvoices[$scope.openInvoices[i].id] = true;
            }
        };

        $scope.uncheckAll = function () {
            for (let i in $scope.openInvoices) {
                $scope.selectedInvoices[$scope.openInvoices[i].id] = false;
            }
        };

        $scope.save = function (expectedPaymentDate, selectedInvoices) {
            $scope.saving = 0;
            $scope.error = null;

            let _expectedPaymentDate = angular.copy(expectedPaymentDate);
            _expectedPaymentDate.date = moment(_expectedPaymentDate.date).unix();

            let invoices = [];
            angular.forEach(selectedInvoices, function (selected, id) {
                if (selected) {
                    for (let i in $scope.openInvoices) {
                        if ($scope.openInvoices[i].id == id) {
                            invoices.push($scope.openInvoices[i]);
                            break;
                        }
                    }
                }
            });

            angular.forEach(invoices, function (invoice) {
                $scope.saving++;
                Invoice.edit(
                    {
                        id: invoice.id,
                    },
                    {
                        expected_payment_date: _expectedPaymentDate,
                    },
                    function () {
                        $scope.saving--;

                        if ($scope.saving === 0) {
                            $modalInstance.close();
                        }
                    },
                    function (result) {
                        $scope.saving--;
                        $scope.error = result.data;
                    },
                );
            });
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        loadInvoices();

        function loadInvoices() {
            $scope.loading = true;
            Invoice.findAll(
                {
                    'filter[customer]': $scope.customer.id,
                    'filter[paid]': false,
                    'filter[closed]': false,
                    'filter[draft]': false,
                    'filter[voided]': false,
                    paginate: 'none',
                    sort: 'date ASC',
                },
                function (invoices) {
                    $scope.loading = false;
                    $scope.openInvoices = [];

                    angular.forEach(invoices, function (invoice) {
                        if (invoice.status != 'pending') {
                            $scope.openInvoices.push(invoice);
                        }
                    });
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
