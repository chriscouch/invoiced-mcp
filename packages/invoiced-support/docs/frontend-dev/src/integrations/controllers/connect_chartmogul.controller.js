(function () {
    'use strict';

    angular.module('app.integrations').controller('ConnectChartMogulController', ConnectChartMogulController);

    ConnectChartMogulController.$inject = ['$scope', '$modalInstance', 'Integration'];

    function ConnectChartMogulController($scope, $modalInstance, Integration) {
        $scope.chartmogul = {
            token: '',
            data_source: '',
            enabled: true,
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

            let params = angular.copy($scope.chartmogul);

            Integration.connect(
                {
                    id: 'chartmogul',
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
