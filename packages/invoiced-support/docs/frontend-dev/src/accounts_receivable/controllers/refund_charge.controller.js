(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('RefundChargeController', RefundChargeController);

    RefundChargeController.$inject = ['$scope', '$modalInstance', 'Charge', 'Money', 'charge'];

    function RefundChargeController($scope, $modalInstance, Charge, Money, charge) {
        $scope.charge = charge;

        $scope.refund = function (type, amount) {
            $scope.saving = true;
            $scope.error = null;

            // parse amount
            if (type === 'full') {
                amount = $scope.maxAmount;
            } else {
                amount = Math.min($scope.maxAmount, amount);
            }

            Charge.refund(
                {
                    id: $scope.charge.id,
                },
                {
                    amount: amount,
                },
                function (refund) {
                    $scope.saving = false;
                    $modalInstance.close(refund);
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

        calculate(charge);

        function calculate(charge) {
            if (charge.amount_refunded > 0) {
                $scope.allowFullRefund = false;
                $scope.type = 'partial';
            } else {
                $scope.allowFullRefund = true;
                $scope.type = 'full';
            }

            let net = Money.round(charge.currency, charge.amount - charge.amount_refunded);
            if (net <= 0) {
                $scope.alreadyRefunded = true;
            }

            $scope.amount = net;
            $scope.maxAmount = net;
        }
    }
})();
