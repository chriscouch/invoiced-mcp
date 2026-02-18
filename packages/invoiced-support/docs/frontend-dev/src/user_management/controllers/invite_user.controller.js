(function () {
    'use strict';

    angular.module('app.user_management').controller('InviteUserController', InviteUserController);

    InviteUserController.$inject = [
        '$scope',
        '$modalInstance',
        'Member',
        'CustomField',
        'company',
        'roles',
        'InvoicedConfig',
    ];

    function InviteUserController($scope, $modalInstance, Member, CustomField, company, roles, InvoicedConfig) {
        $scope.company = company;
        $scope.roles = roles;
        $scope.role = 'employee';
        $scope.restrictionMode = 'none';
        $scope.restrictionsList = [{ field: null, value: null }];

        $scope.permissions = {};
        angular.forEach(roles, function (role) {
            $scope.permissions[role.id] = buildPermissions(role);
            role.score = score(role);
        });

        $scope.invite = function (email, first_name, last_name, role, restrictionMode, restrictionsList) {
            $scope.saving = true;
            $scope.error = null;

            let restrictions = null;
            if (restrictionMode === 'custom_field' && restrictionsList.length > 0) {
                restrictions = {};
                angular.forEach(restrictionsList, function (entry) {
                    if (typeof restrictions[entry.field] === 'undefined') {
                        restrictions[entry.field] = [];
                    }

                    restrictions[entry.field].push(entry.value);
                });
            }

            Member.create(
                {
                    email: email,
                    first_name: first_name,
                    last_name: last_name,
                    role: role,
                    restriction_mode: restrictionMode,
                    restrictions: restrictions,
                },
                function (member) {
                    $scope.saving = false;
                    $modalInstance.close(member);
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

        $scope.addRestriction = function () {
            $scope.restrictionsList.push({
                field: null,
                value: null,
            });
        };

        $scope.deleteRestriction = function ($index) {
            $scope.restrictionsList.splice($index, 1);
        };

        loadCustomFields();

        function buildPermissions(role) {
            let p = ['Read All Data'];

            angular.forEach(InvoicedConfig.permissions, function (permission) {
                if (role[permission.id]) {
                    p.push(permission.name);
                }
            });

            return p;
        }

        // ranks how permissive a role is
        function score(role) {
            let points = 0;
            angular.forEach(role, function (v) {
                if (v === true) {
                    points++;
                }
            });
            return points;
        }

        function loadCustomFields() {
            CustomField.all(
                function (customFields) {
                    $scope.customFields = [];
                    angular.forEach(customFields, function (customField) {
                        if (customField.object === 'customer') {
                            $scope.customFields.push(customField);
                        }
                    });
                },
                function (result) {
                    $scope.error = result.data;
                },
            );
        }
    }
})();
