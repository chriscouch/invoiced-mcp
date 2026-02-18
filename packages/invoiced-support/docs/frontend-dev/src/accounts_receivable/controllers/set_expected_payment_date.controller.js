/* globals moment */
(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('SetExpectedPaymentDateController', SetExpectedPaymentDateController);

    SetExpectedPaymentDateController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'Invoice',
        'invoice',
        'expectedPaymentDate',
        'DatePickerService',
    ];

    function SetExpectedPaymentDateController(
        $scope,
        $modalInstance,
        selectedCompany,
        Invoice,
        invoice,
        expectedPaymentDate,
        DatePickerService,
    ) {
        $scope.invoice = invoice;
        $scope.company = selectedCompany;

        if (expectedPaymentDate) {
            $scope.expectedPaymentDate = angular.copy(expectedPaymentDate);

            $scope.hasExpectedPaymentDate = !!$scope.expectedPaymentDate.date;

            if (!$scope.expectedPaymentDate.date) {
                $scope.expectedPaymentDate.date = new Date();
            }

            if (!$scope.expectedPaymentDate.method) {
                $scope.expectedPaymentDate.method = 'other';
            }
        } else {
            $scope.expectedPaymentDate = {
                date: null,
                method: 'other',
                reference: null,
            };

            $scope.hasExpectedPaymentDate = false;
        }

        $scope.dateOptions = DatePickerService.getOptions({
            minDate: 0,
            maxDate: '+3M',
        });

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
        };

        $scope.save = function (expectedPaymentDate) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                date: moment(expectedPaymentDate.date).unix(),
                method: expectedPaymentDate.method,
                reference: expectedPaymentDate.reference,
            };

            Invoice.edit(
                {
                    id: $scope.invoice.id,
                },
                {
                    expected_payment_date: params,
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close(expectedPaymentDate);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        };

        $scope.clearExpectedDate = function () {
            $scope.saving = true;
            $scope.error = null;

            Invoice.edit(
                {
                    id: $scope.invoice.id,
                },
                {
                    expected_payment_date: {
                        date: null,
                        method: null,
                        reference: null,
                    },
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close(null);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });
    }
})();
