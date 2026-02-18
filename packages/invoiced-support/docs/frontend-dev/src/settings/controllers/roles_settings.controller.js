/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('RolesSettingsController', RolesSettingsController);

    RolesSettingsController.$inject = ['$scope', '$modal', 'InvoicedConfig', 'Role', 'Core', 'CurrentUser'];

    function RolesSettingsController($scope, $modal, InvoicedConfig, Role, Core) {
        $scope.roles = [];
        $scope.deleting = {};

        $scope.newRole = function newRole() {
            const modalInstance = $modal.open({
                templateUrl: 'user_management/views/edit-role.html',
                controller: 'EditRoleController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    role: function () {
                        return {};
                    },
                },
            });

            modalInstance.result.then(
                function (newRole) {
                    processRole(newRole);
                    $scope.roles.push(newRole);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.edit = function (role) {
            const modalInstance = $modal.open({
                templateUrl: 'user_management/views/edit-role.html',
                controller: 'EditRoleController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    role: function () {
                        return role;
                    },
                },
            });

            modalInstance.result.then(
                function (newRole) {
                    processRole(newRole);
                    if (typeof role.id === 'undefined') {
                        $scope.roles.push(newRole);
                    } else {
                        angular.extend(role, newRole);
                    }
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.clone = function (role) {
            let newRole = angular.copy(role);
            delete newRole.id;
            delete newRole.name;
            $scope.edit(newRole);
        };

        $scope.delete = function (role) {
            let roleId = role.id;

            vex.dialog.confirm({
                message: 'Are you sure you want to remove this role?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[roleId] = true;

                        Role.delete(
                            {
                                id: role.id,
                            },
                            function () {
                                $scope.deleting[roleId] = false;

                                // remove the role locally
                                for (let i in $scope.roles) {
                                    if ($scope.roles[i].id === roleId) {
                                        $scope.roles.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                $scope.deleting[roleId] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('Roles');
        loadRoles();

        function loadRoles() {
            $scope.loading = true;

            Role.findAll(
                { paginate: 'none' },
                function (roles) {
                    angular.forEach(roles, processRole);
                    $scope.roles = roles;

                    $scope.loading = false;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function processRole(role) {
            role.permissions = [];
            angular.forEach(InvoicedConfig.permissions, function (permission) {
                if (role[permission.id]) {
                    role.permissions.push(permission.name);
                }
            });
        }
    }
})();
