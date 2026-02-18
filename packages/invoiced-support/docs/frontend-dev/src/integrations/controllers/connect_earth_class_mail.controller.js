(function () {
    'use strict';

    angular.module('app.integrations').controller('ConnectEarthClassMailController', ConnectEarthClassMailController);

    ConnectEarthClassMailController.$inject = ['$scope', '$modalInstance', 'Integration'];

    function ConnectEarthClassMailController($scope, $modalInstance, Integration) {
        $scope.earthClassMail = {
            api_key: '',
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

            let params = angular.copy($scope.earthClassMail);

            Integration.connect(
                {
                    id: 'earth_class_mail',
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
