(function () {
    'use strict';

    angular.module('app.payment_setup').controller('SetupPayPalPaymentsController', SetupPayPalPaymentsController);

    SetupPayPalPaymentsController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'PaymentMethod',
        'Core',
        'method',
    ];

    function SetupPayPalPaymentsController($scope, $modalInstance, selectedCompany, PaymentMethod, Core, method) {
        $scope.method = angular.copy(method);
        $scope.company = angular.copy(selectedCompany);
        $scope.orderOptions = [
            { value: 0, name: 'Default Order' },
            { value: 40, name: '1st' },
            { value: 30, name: '2nd' },
            { value: 20, name: '3rd' },
            { value: 10, name: '4th' },
        ];

        $scope.disable = function () {
            $scope.saving = true;
            $scope.error = null;

            PaymentMethod.edit(
                {
                    id: $scope.method.id,
                },
                {
                    enabled: false,
                },
                function () {
                    $scope.saving = false;
                    $scope.method.enabled = false;
                    $modalInstance.close($scope.method);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        };

        $scope.enable = function (paypalAddress) {
            $scope.saving = true;
            $scope.error = null;

            PaymentMethod.edit(
                {
                    id: $scope.method.id,
                },
                {
                    enabled: true,
                    meta: paypalAddress,
                    min: $scope.method.min,
                    max: $scope.method.max,
                    order: $scope.method.order,
                },
                function (returned) {
                    $scope.saving = false;
                    $modalInstance.close(returned);
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
