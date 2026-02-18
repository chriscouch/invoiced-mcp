(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('AddDepositController', AddDepositController);

    AddDepositController.$inject = ['$scope', '$modalInstance', '$modal', 'selectedCompany', 'Core', 'document'];

    function AddDepositController($scope, $modalInstance, $modal, selectedCompany, Core, doc) {
        $scope.document = angular.copy(doc);
        $scope.amount = doc.deposit;
        $scope.calculatedAmount = doc.deposit;
        $scope.isPercent = false;

        $scope.save = function (amount) {
            $modalInstance.close(amount);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.$watch('isPercent', function (isPercent) {
            if (isPercent) {
                // switching from $ -> %
                if (doc.total > 0) {
                    $scope.amount = Math.round(($scope.calculatedAmount / doc.total) * 100);
                } else {
                    $scope.amount = 0;
                }
            } else {
                // switching from % -> $
                $scope.amount = $scope.calculatedAmount;
            }
        });

        $scope.$watch('amount', function (amount) {
            if ($scope.isPercent) {
                $scope.calculatedAmount = (amount / 100.0) * doc.total;
            } else {
                $scope.calculatedAmount = amount;
            }
        });
    }
})();
