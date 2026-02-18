/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('ContactRoleSettingsController', ContactRoleSettingsController);

    ContactRoleSettingsController.$inject = [
        '$scope',
        '$modal',
        'selectedCompany',
        'Core',
        'Customer',
        'LeavePageWarning',
        'Feature',
    ];

    function ContactRoleSettingsController($scope, $modal, selectedCompany, Core, Customer, LeavePageWarning, Feature) {
        /**
         * Initialization
         */

        $scope.hasFeature = Feature.hasFeature('smart_chasing');
        $scope.roles = [];
        $scope.loading = 0;
        $scope.deleting = {};

        Core.setTitle('Contact Roles');
        loadRoles();

        $scope.newRole = function () {
            const modalInstance = $modal.open({
                templateUrl: 'settings/views/edit-contact-role.html',
                controller: 'EditContactRoleController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    role: function () {
                        return null;
                    },
                },
            });

            modalInstance.result.then(
                function (role) {
                    LeavePageWarning.unblock();

                    $scope.roles.push(role);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.editRole = function (role) {
            const modalInstance = $modal.open({
                templateUrl: 'settings/views/edit-contact-role.html',
                controller: 'EditContactRoleController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    role: function () {
                        return role;
                    },
                },
            });

            modalInstance.result.then(
                function (_role) {
                    LeavePageWarning.unblock();
                    // update role details
                    role.name = _role.name;
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        /**
         * Deletes a role.
         */
        $scope.delete = function (role) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this contact role?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[role.id] = true;
                        $scope.error = null;

                        Customer.deleteContactRole(
                            {
                                id: role.id,
                            },
                            function () {
                                delete $scope.deleting[role.id];

                                Core.flashMessage('The contact role, ' + role.name + ', has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.roles) {
                                    if ($scope.roles[i].id === role.id) {
                                        $scope.roles.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                delete $scope.deleting[role.id];
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        /**
         * Loads all contact roles.
         */
        function loadRoles() {
            $scope.loading++;

            Customer.contactRoles(
                function (roles) {
                    $scope.loading--;
                    $scope.roles = roles;
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
