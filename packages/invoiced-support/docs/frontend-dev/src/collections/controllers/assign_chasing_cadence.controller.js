(function () {
    'use strict';

    angular.module('app.collections').controller('AssignChasingCadenceController', AssignChasingCadenceController);

    AssignChasingCadenceController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        'ChasingCadence',
        'Customer',
        'Core',
        'customer',
        'cadence',
        'selectedCompany',
    ];

    function AssignChasingCadenceController(
        $scope,
        $modalInstance,
        $modal,
        ChasingCadence,
        Customer,
        Core,
        customer,
        cadence,
        selectedCompany,
    ) {
        $scope.company = selectedCompany;
        $scope.customer = angular.copy(customer);
        $scope.hasCadence = !!cadence;
        $scope.cadence = cadence;
        $scope.nextStep = customer.next_chase_step;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.changedCadence = function () {
            if ($scope.cadence.steps.length > 0) {
                $scope.nextStep = $scope.cadence.steps[0].id;
            } else {
                $scope.nextStep = null;
            }
        };

        $scope.save = function (cadence, nextStepId) {
            if (!$scope.hasCadence) {
                cadence = null;
                nextStepId = null;
            }

            $scope.saving = true;
            Customer.edit(
                {
                    id: customer.id,
                },
                {
                    chase: $scope.hasCadence,
                    chasing_cadence: cadence ? cadence.id : null,
                    next_chase_step: nextStepId,
                },
                function (updatedCustomer) {
                    $scope.saving = false;
                    updatedCustomer.chasing_cadence = cadence;
                    $modalInstance.close(updatedCustomer);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        load();

        function load() {
            $scope.loading = true;

            ChasingCadence.findAll(
                {
                    'filter[paused]': false,
                    paginate: 'none',
                },
                function (cadences) {
                    $scope.loading = false;
                    $scope.cadences = cadences;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
