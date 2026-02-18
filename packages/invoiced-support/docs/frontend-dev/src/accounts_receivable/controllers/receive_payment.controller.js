(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('ReceivePaymentController', ReceivePaymentController);

    ReceivePaymentController.$inject = ['$scope', '$modalInstance', 'Permission', 'ReceivePaymentForm', 'options'];

    function ReceivePaymentController($scope, $modalInstance, Permission, ReceivePaymentForm, options) {
        $scope.type = Permission.hasPermission('payments.create') ? 'cash_receipt' : 'process_payment';
        $scope.options = {
            preselected: null,
            customer: null,
            amount: null,
            currency: null,
            appliedCredits: null,
        };
        angular.extend($scope.options, options);

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.complete = $modalInstance.close;
        ReceivePaymentForm.clear();
    }
})();
