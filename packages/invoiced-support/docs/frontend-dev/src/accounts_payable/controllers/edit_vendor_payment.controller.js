/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('EditVendorPaymentController', EditVendorPaymentController);

    EditVendorPaymentController.$inject = ['$scope', '$modalInstance', 'DatePickerService', 'VendorPayment', 'payment'];

    function EditVendorPaymentController($scope, $modalInstance, DatePickerService, VendorPayment, payment) {
        $scope.payment = angular.copy(payment);
        $scope.dateOptions = DatePickerService.getOptions();

        $scope.save = function (input) {
            $scope.saving = true;
            $scope.error = null;

            VendorPayment.edit(
                {
                    id: payment.id,
                },
                {
                    date: moment(input.date).format('YYYY-MM-DD'),
                    payment_method: input.payment_method,
                    reference: input.reference,
                    notes: input.notes,
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
