(function () {
    'use strict';

    angular.module('app.billing').controller('TrialEndedController', TrialEndedController);

    TrialEndedController.$inject = ['$scope', '$state', '$modal', 'CurrentUser', 'Core', 'Feature', 'selectedCompany'];

    function TrialEndedController($scope, $state, $modal, CurrentUser, Core, Feature, selectedCompany) {
        $scope.user = CurrentUser.profile;
        $scope.company = selectedCompany;
        $scope.companies = CurrentUser.companies;
        $scope.upgradeUrl = Core.upgradeUrl($scope.company, $scope.user, Feature.hasFeature('not_activated'));

        if (typeof $scope.company.billing == 'undefined') {
            $state.go('index');
            return;
        }

        Core.setTitle('Trial Ended');

        $scope.switchCompany = function () {
            $modal.open({
                templateUrl: 'core/views/switch-business.html',
                controller: 'SwitchBusinessController',
                size: 'lg',
            });
        };
    }
})();
