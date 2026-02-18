(function () {
    'use strict';

    angular.module('app.integrations').controller('ConnectNetSuiteController', ConnectNetSuiteController);

    ConnectNetSuiteController.$inject = ['$scope', '$modalInstance', 'Integration'];

    function ConnectNetSuiteController($scope, $modalInstance, Integration) {
        $scope.netsuite = {
            name: 'NetSuite account',
            account_id: '',
            token: '',
            token_secret: '',
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

            let params = angular.copy($scope.netsuite);
            params.account_id = $.trim(params.account_id);
            params.token = $.trim(params.token);
            params.token_secret = $.trim(params.token_secret);

            Integration.connect(
                {
                    id: 'netsuite',
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
