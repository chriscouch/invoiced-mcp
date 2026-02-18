(function () {
    'use strict';

    angular.module('app.billing').directive('activateAccountBanner', activateAccountBanner);

    function activateAccountBanner() {
        return {
            restrict: 'E',
            templateUrl: 'billing/views/activate-account-banner.html',
            controller: [
                '$scope',
                'selectedCompany',
                'CurrentUser',
                'Core',
                'Feature',
                function ($scope, selectedCompany, CurrentUser, Core, Feature) {
                    $scope.activateUrl = Core.upgradeUrl(
                        selectedCompany,
                        CurrentUser.profile,
                        Feature.hasFeature('not_activated'),
                    );
                },
            ],
        };
    }
})();
