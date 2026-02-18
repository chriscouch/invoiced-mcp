(function () {
    'use strict';

    angular.module('app.settings').controller('SetupCustomDomainController', SetupCustomDomainController);

    SetupCustomDomainController.$inject = ['$scope', '$modalInstance', 'Company', 'selectedCompany'];

    function SetupCustomDomainController($scope, $modalInstance, Company, selectedCompany) {
        $scope.hasCustomDomain =
            selectedCompany.url.indexOf('invoiced.com') === -1 &&
            selectedCompany.url.indexOf('invoiced.localhost') === -1;

        $scope.save = function (customDomain) {
            $scope.error = null;
            $scope.saving = true;
            Company.setCustomDomain(
                {
                    id: selectedCompany.id,
                },
                {
                    domain: customDomain,
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close();
                },
                function (result) {
                    $scope.error = result.data;
                    $scope.saving = false;
                },
            );
        };

        $scope.delete = function () {
            $scope.error = null;
            $scope.saving = true;
            Company.edit(
                {
                    id: selectedCompany.id,
                },
                {
                    custom_domain: null,
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close();
                },
                function (result) {
                    $scope.error = result.data;
                    $scope.saving = false;
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
