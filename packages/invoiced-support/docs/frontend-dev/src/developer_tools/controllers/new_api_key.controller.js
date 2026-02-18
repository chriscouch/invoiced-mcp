(function () {
    'use strict';

    angular.module('app.developer_tools').controller('NewApiKeyController', NewApiKeyController);

    NewApiKeyController.$inject = ['$scope', '$modalInstance', 'selectedCompany', 'ApiKey', 'model'];

    function NewApiKeyController($scope, $modalInstance, selectedCompany, ApiKey, model) {
        $scope.model = model;

        $scope.company = selectedCompany;

        $scope.save = function (model) {
            $scope.saving = true;
            $scope.error = null;

            ApiKey.create(
                {
                    description: model.description,
                },
                function (apiKey) {
                    $scope.saving = false;
                    $modalInstance.close(apiKey);
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
    }
})();
