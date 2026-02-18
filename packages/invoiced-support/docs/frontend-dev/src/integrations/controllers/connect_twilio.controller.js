(function () {
    'use strict';

    angular.module('app.integrations').controller('ConnectTwilioController', ConnectTwilioController);

    ConnectTwilioController.$inject = ['$scope', '$modalInstance', 'Integration'];

    function ConnectTwilioController($scope, $modalInstance, Integration) {
        $scope.twilio = {
            account_sid: '',
            auth_token: '',
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

            let params = angular.copy($scope.twilio);

            Integration.connect(
                {
                    id: 'twilio',
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
