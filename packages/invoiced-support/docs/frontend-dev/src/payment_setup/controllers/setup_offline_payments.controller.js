(function () {
    'use strict';

    angular.module('app.payment_setup').controller('SetupOfflinePaymentsController', SetupOfflinePaymentsController);

    SetupOfflinePaymentsController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'PaymentMethod',
        'Core',
        'methods',
    ];

    function SetupOfflinePaymentsController($scope, $modalInstance, selectedCompany, PaymentMethod, Core, methods) {
        $scope.methods = angular.copy(methods);
        $scope.country = '';
        $scope.loading = 0;

        let offlineMethods = [
            $scope.methods.wire_transfer,
            $scope.methods.check,
            $scope.methods.cash,
            $scope.methods.other,
        ];

        $scope.save = function () {
            $scope.error = null;

            $scope.saving = offlineMethods.length;
            angular.forEach(offlineMethods, function (_method) {
                let params = {
                    enabled: _method.enabled,
                    meta: _method.meta,
                    country: $scope.country,
                };
                PaymentMethod.edit(
                    {
                        id: _method.id,
                    },
                    params,
                    function (method) {
                        $scope.saving--;
                        angular.extend($scope.methods[method.id], method);
                        if (!$scope.saving) {
                            $modalInstance.close({
                                methods: $scope.methods,
                                country: $scope.country,
                            });
                        }
                    },
                    function (result) {
                        $scope.saving--;
                        $scope.error = result.data;
                    },
                );
            });
        };

        $scope.changeCountry = function (country) {
            angular.forEach(offlineMethods, function (_method) {
                $scope.loading++;
                let params = {
                    id: _method.id,
                    country: country,
                };
                PaymentMethod.find(
                    params,
                    function (method) {
                        angular.extend($scope.methods[method.id], method);
                        $scope.country = country;
                        $scope.loading--;
                    },
                    function (result) {
                        $scope.error = result.data;
                        $scope.loading--;
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
    }
})();
