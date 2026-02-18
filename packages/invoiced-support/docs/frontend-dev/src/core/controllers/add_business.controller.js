(function () {
    'use strict';

    angular.module('app.core').controller('AddBusinessController', AddBusinessController);

    AddBusinessController.$inject = [
        '$scope',
        '$modalInstance',
        '$window',
        '$state',
        '$timeout',
        'InvoicedConfig',
        'Core',
        'CSRF',
        'Company',
        'CurrentUser',
        'selectedCompany',
    ];

    function AddBusinessController(
        $scope,
        $modalInstance,
        $window,
        $state,
        $timeout,
        InvoicedConfig,
        Core,
        CSRF,
        Company,
        CurrentUser,
        selectedCompany,
    ) {
        $scope.email = CurrentUser.profile.email;
        $scope.country = selectedCompany.country;
        $scope.isSandbox = InvoicedConfig.environment === 'sandbox';
        $scope.hasAtLeastOneCompany = CurrentUser.companies.length > 0;

        $scope.create = function (email, country) {
            $scope.saving = true;
            $scope.error = null;
            CSRF(function () {
                Company.create(
                    {
                        country: country,
                        email: email,
                        tenant_id: selectedCompany.id,
                    },
                    function (company) {
                        $scope.saving = false;
                        CurrentUser.setSelectedCompanyId(company.id);
                        $state.go('index');
                        $timeout(() => {
                            $window.location.reload();
                        });
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data.message;
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
    }
})();
