(function () {
    'use strict';

    angular.module('app.auth').controller('Remove2FAController', Remove2FAController);

    Remove2FAController.$inject = ['$scope', '$modalInstance', 'CSRF', 'CurrentUser'];

    function Remove2FAController($scope, $modalInstance, CSRF, CurrentUser) {
        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.remove = remove;

        function remove(password, token) {
            $scope.saving = true;
            $scope.error = false;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                CurrentUser.remove2FA(
                    {
                        password: password,
                        token: token,
                    },
                    function () {
                        $scope.saving = false;
                        $modalInstance.close(false);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            });
        }
    }
})();
