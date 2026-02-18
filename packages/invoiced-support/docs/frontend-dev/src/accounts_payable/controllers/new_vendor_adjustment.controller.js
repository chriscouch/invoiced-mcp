/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('NewVendorAdjustmentController', NewVendorAdjustmentController);

    NewVendorAdjustmentController.$inject = [
        '$scope',
        '$modalInstance',
        'DatePickerService',
        'VendorAdjustment',
        'doc',
    ];

    function NewVendorAdjustmentController($scope, $modalInstance, DatePickerService, VendorAdjustment, doc) {
        $scope.doc = doc;
        $scope.adjustment = {
            currency: doc.currency,
            date: new Date(),
        };
        $scope.dateOptions = DatePickerService.getOptions();

        $scope.save = function (input) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                vendor: parseInt(doc.vendor.id),
                currency: input.currency,
                amount: input.amount,
                date: moment(input.date).format('YYYY-MM-DD'),
                notes: input.notes,
            };
            params[doc.object] = doc.id;
            VendorAdjustment.create(
                params,
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
