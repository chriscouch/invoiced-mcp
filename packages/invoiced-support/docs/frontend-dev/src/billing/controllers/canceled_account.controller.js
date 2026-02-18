(function () {
    'use strict';

    angular.module('app.billing').controller('CanceledAccountController', CanceledAccountController);

    CanceledAccountController.$inject = ['$scope', '$state', '$modal', 'Core', 'CurrentUser', 'selectedCompany'];

    function CanceledAccountController($scope, $state, $modal, Core, CurrentUser, selectedCompany) {
        if (selectedCompany.billing.status === 'trialing') {
            $state.go('billing.trial_ended');
        }

        Core.setTitle('Canceled Account');
        $scope.company = selectedCompany;
        $scope.companies = CurrentUser.companies;
        $scope.activateUrl = Core.upgradeUrl(selectedCompany, CurrentUser.profile, true);

        $scope.switchCompany = function () {
            $modal.open({
                templateUrl: 'core/views/switch-business.html',
                controller: 'SwitchBusinessController',
                size: 'lg',
            });
        };
    }
})();
