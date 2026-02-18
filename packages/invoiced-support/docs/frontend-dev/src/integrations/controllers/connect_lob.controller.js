(function () {
    'use strict';

    angular.module('app.integrations').controller('ConnectLobController', ConnectLobController);

    ConnectLobController.$inject = ['$scope', '$modalInstance', 'Integration'];

    function ConnectLobController($scope, $modalInstance, Integration) {
        $scope.lob = {
            key: '',
        };

        $scope.save = createAccount;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function createAccount() {
            $scope.saving = true;
            $scope.error = null;

            let params = angular.copy($scope.lob);

            Integration.connect(
                {
                    id: 'lob',
                },
                params,
                function () {
                    $scope.saving = false;
                    $modalInstance.close();
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
