(function () {
    'use strict';

    angular
        .module('app.collections')
        .controller('MassAssignChasingCadenceController', MassAssignChasingCadenceController);

    MassAssignChasingCadenceController.$inject = ['$scope', '$modalInstance', 'ChasingCadence', 'cadence'];

    function MassAssignChasingCadenceController($scope, $modalInstance, ChasingCadence, cadence) {
        $scope.cadence = cadence;
        $scope.accounts = [];
        $scope.excludeCustomerIds = [];
        $scope.nextStep = cadence.steps[0].id;

        $scope.save = function (cadence, accounts, nextStep) {
            $scope.saving = true;
            $scope.error = null;

            let ids = [];
            angular.forEach(accounts, function (customer) {
                ids.push(customer.id);
            });

            ChasingCadence.assign(
                {
                    id: cadence.id,
                },
                {
                    customers: ids,
                    next_step: nextStep,
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close(accounts.length);
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

        $scope.addAccount = function (customer) {
            $scope.accounts.push(customer);
            $scope.excludeCustomerIds.push(customer.id);
        };

        $scope.deleteAccount = function (customer) {
            for (let i in $scope.accounts) {
                if ($scope.accounts[i].id == customer.id) {
                    $scope.accounts.splice(i, 1);
                    $scope.excludeCustomerIds.splice(i, 1);
                    break;
                }
            }
        };

        $scope.newCustomer = null;
        $scope.$watch('newCustomer', function (customer) {
            if (typeof customer !== 'object' || !customer || !customer.id) {
                return;
            }

            $scope.addAccount(customer);
            $scope.newCustomer = null;
        });
    }
})();
