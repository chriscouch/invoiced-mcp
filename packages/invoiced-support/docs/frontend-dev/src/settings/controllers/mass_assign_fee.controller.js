(function () {
    'use strict';

    angular.module('app.settings').controller('MassAssignFeeController', MassAssignFeeController);

    MassAssignFeeController.$inject = ['$scope', '$modalInstance', 'LateFeeSchedule', 'schedule'];

    function MassAssignFeeController($scope, $modalInstance, LateFeeSchedule, schedule) {
        $scope.fee = schedule;
        $scope.accounts = [];
        $scope.excludeCustomerIds = [];

        $scope.save = function () {
            $scope.saving = true;
            $scope.error = null;

            let ids = [];
            angular.forEach($scope.accounts, function (customer) {
                ids.push(customer.id);
            });

            LateFeeSchedule.assign(
                {
                    id: schedule.id,
                },
                {
                    customers: ids,
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close($scope.accounts.length);
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
                if (!$scope.accounts.hasOwnProperty(i)) {
                    continue;
                }
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
