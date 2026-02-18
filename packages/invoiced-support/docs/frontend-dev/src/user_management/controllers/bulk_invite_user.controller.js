(function () {
    'use strict';

    angular.module('app.user_management').controller('BulkInviteUserController', BulkInviteUserController);

    BulkInviteUserController.$inject = ['$scope', '$modalInstance', 'Member', 'Core', 'company', 'roles'];

    function BulkInviteUserController($scope, $modalInstance, Member, Core, company, roles) {
        $scope.errors = [];

        $scope.invite = function (usersToAdd) {
            $scope.saving = true;
            $scope.errors = [];

            let requests = buildRequests(usersToAdd);
            let saved = 0;
            let successful = 0;
            if ($scope.errors.length > 0) {
                $scope.saving = false;
                return;
            }

            angular.forEach(requests, function (request) {
                Member.create(
                    request,
                    function () {
                        saved++;
                        successful++;
                        closeIfFinished(successful, saved, requests);
                    },
                    function (result) {
                        saved++;
                        // If the user is already a member we will ignore the error message.
                        let errorMessage = request.email + ': ' + result.data.message;
                        if (errorMessage.indexOf('already a member') === -1) {
                            $scope.errors.push(errorMessage);
                        }

                        closeIfFinished(successful, saved, requests);
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

        function buildRequests(usersToAdd) {
            let requests = [];
            let rows = usersToAdd.split('\n');
            angular.forEach(rows, function (row) {
                let parts = row.split('\t');

                // Must have 4 columns
                if (parts.length !== 4) {
                    $scope.errors.push(
                        'Expecting 4 tab-separated columns but only given ' + parts.length + ' in this row: ' + row,
                    );
                    return;
                }

                // Skip if first element starts with "email" because
                // this indicates that it is the header row
                if (parts[0].toLowerCase().indexOf('email') === 0) {
                    return;
                }

                // Look up role ID based on name
                let roleId = null;
                angular.forEach(roles, function (role) {
                    if (role.name === parts[3]) {
                        roleId = role.id;
                        return false;
                    }
                });

                if (!roleId) {
                    $scope.errors.push('Role does not exist: ' + parts[3]);
                    return;
                }

                requests.push({
                    email: parts[0],
                    first_name: parts[1],
                    last_name: parts[2],
                    role: roleId,
                });
            });
            return requests;
        }

        function closeIfFinished(successful, saved, requests) {
            if (saved < requests.length) {
                return;
            }

            $scope.saving = false;
            if (successful > 0) {
                Core.flashMessage('Successfully invited ' + successful + ' user(s)', 'success');
                Member.clearCache();
            }
            if ($scope.errors.length === 0) {
                $modalInstance.close();
            }
        }
    }
})();
