(function () {
    'use strict';

    angular.module('app.integrations').controller('ConnectAvalaraController', ConnectAvalaraController);

    ConnectAvalaraController.$inject = ['$scope', '$modalInstance', 'InvoicedConfig', 'Integration'];

    function ConnectAvalaraController($scope, $modalInstance, InvoicedConfig, Integration) {
        $scope.credentials = {
            account_id: '',
            license_key: '',
        };
        $scope.isProduction = InvoicedConfig.environment === 'production';
        $scope.step = 1;

        $scope.validate = validateAccount;
        $scope.save = createAccount;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function validateAccount(credentials) {
            $scope.saving = true;
            $scope.error = null;

            Integration.avalaraCompanies(
                credentials,
                function (companies) {
                    $scope.saving = false;
                    $scope.companies = companies;
                    $scope.step = 2;
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function createAccount(credentials, company) {
            $scope.saving = true;
            $scope.error = null;

            Integration.connect(
                {
                    id: 'avalara',
                },
                {
                    name: company.name,
                    account_id: credentials.account_id,
                    license_key: credentials.license_key,
                    company_code: company.code,
                },
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
