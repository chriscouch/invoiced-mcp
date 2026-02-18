(function () {
    'use strict';

    angular.module('app.settings').controller('EditContactRoleController', EditContactRoleController);

    EditContactRoleController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'Core',
        'LeavePageWarning',
        'Feature',
        'Customer',
        'role',
    ];

    function EditContactRoleController(
        $scope,
        $modalInstance,
        selectedCompany,
        Core,
        LeavePageWarning,
        Feature,
        Customer,
        role,
    ) {
        /**
         * Initialization
         */

        $scope.saving = false;
        $scope.hasFeature = Feature.hasFeature('smart_chasing');
        $scope.role = {
            id: role ? role.id : null,
            name: role ? role.name : '',
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.save = function (role) {
            if (!$scope.role.id) {
                saveNewRole(role);
            } else {
                editExistingRole(role);
            }
        };

        function saveNewRole(role) {
            $scope.saving = true;
            Customer.createContactRole(
                {
                    name: role.name,
                },
                function (_role) {
                    $scope.saving = false;
                    $modalInstance.close(_role);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function editExistingRole(role) {
            $scope.saving = true;
            Customer.editContactRole(
                {
                    id: role.id,
                },
                {
                    name: role.name,
                },
                function (_role) {
                    $scope.saving = false;
                    $modalInstance.close(_role);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
