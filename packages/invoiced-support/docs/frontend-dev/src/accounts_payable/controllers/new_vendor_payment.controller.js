/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('NewVendorPaymentController', NewVendorPaymentController);

    NewVendorPaymentController.$inject = ['$scope', '$modalInstance', 'DatePickerService', 'VendorPayment', 'bill'];

    function NewVendorPaymentController($scope, $modalInstance, DatePickerService, VendorPayment, bill) {
        $scope.bill = bill;
        $scope.payment = {
            currency: bill.currency,
            payment_method: 'other',
            date: new Date(),
        };

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.save = function (input) {
            $scope.saving = true;
            $scope.error = null;

            VendorPayment.create(
                {
                    vendor: parseInt(bill.vendor.id),
                    currency: input.currency,
                    amount: input.amount,
                    date: moment(input.date).format('YYYY-MM-DD'),
                    payment_method: input.payment_method,
                    reference: input.reference,
                    notes: input.notes,
                    applied_to: [
                        {
                            bill: bill.id,
                            amount: input.amount,
                        },
                    ],
                },
                function (payment) {
                    $scope.saving = false;
                    $modalInstance.close(payment);
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
