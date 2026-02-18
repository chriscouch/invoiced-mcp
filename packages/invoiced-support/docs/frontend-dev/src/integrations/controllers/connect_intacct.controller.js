(function () {
    'use strict';

    angular.module('app.integrations').controller('ConnectIntacctController', ConnectIntacctController);

    ConnectIntacctController.$inject = ['$scope', '$modalInstance', 'Integration'];

    function ConnectIntacctController($scope, $modalInstance, Integration) {
        $scope.intacct = {
            name: 'Intacct account',
            intacct_company_id: '',
            user_id: '',
            user_password: '',
            sender_id: '',
            sender_password: '',
            entity_id: '',
        };
        $scope.isMultiEntity = false;

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

            let params = angular.copy($scope.intacct);

            if (!$scope.isMultiEntity) {
                params.entity_id = null;
            }

            Integration.connect(
                {
                    id: 'intacct',
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
