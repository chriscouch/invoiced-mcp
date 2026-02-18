(function () {
    'use strict';

    angular.module('app.user_management').controller('EditRoleController', EditRoleController);

    EditRoleController.$inject = ['$scope', '$modalInstance', 'Role', 'role', 'InvoicedConfig'];

    function EditRoleController($scope, $modalInstance, Role, role, InvoicedConfig) {
        if (role) {
            $scope.role = angular.copy(role);
        } else {
            $scope.role = {};
        }

        $scope.permissions = InvoicedConfig.permissions;
        $scope.selectedPermissions = {};
        populateSelectedPermissions();

        $scope.save = function (role) {
            $scope.saving = true;
            $scope.error = null;
            let params = $scope.selectedPermissions;
            params.name = role.name;

            if (role.id) {
                Role.edit(
                    {
                        id: role.id,
                    },
                    params,
                    function (role) {
                        $scope.saving = false;
                        $modalInstance.close(role);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                Role.create(
                    params,
                    function (role) {
                        $scope.saving = false;
                        $modalInstance.close(role);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.noSelectedEvents = function () {
            for (let i in $scope.selectedPermissions) {
                if ($scope.selectedPermissions[i]) {
                    return false;
                }
            }
            return true;
        };

        $scope.atLeastOneSelectedEvent = function () {
            for (let i in $scope.selectedPermissions) {
                if ($scope.selectedPermissions[i]) {
                    return true;
                }
            }
            return false;
        };

        $scope.checkAll = function checkAll() {
            for (let i in $scope.permissions) {
                $scope.selectedPermissions[$scope.permissions[i].id] = true;
            }
        };

        $scope.uncheckAll = function uncheckAll() {
            for (let i in $scope.permissions) {
                $scope.selectedPermissions[$scope.permissions[i].id] = false;
            }
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function populateSelectedPermissions() {
            angular.forEach($scope.permissions, function (permission) {
                $scope.selectedPermissions[permission.id] = $scope.role[permission.id];
            });
        }
    }
})();
