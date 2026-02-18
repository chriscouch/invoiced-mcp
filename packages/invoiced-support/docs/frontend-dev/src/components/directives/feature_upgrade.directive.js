(function () {
    'use strict';

    angular.module('app.components').directive('featureUpgrade', featureUpgrade);

    function featureUpgrade() {
        return {
            restrict: 'E',
            template:
                '<div class="feature-upgrade {{upgradeClass}}">' +
                '<h3>Please upgrade</h3>' +
                '<div class="text">This feature is not available on your current plan.</div>' +
                '<div class="btns hidden">' +
                '<a href="{{upgradeUrl}}" class="btn btn-success" target="_blank">Upgrade Account</a>' +
                '</div>' +
                '</div>',
            scope: {
                upgradeClass: '=?',
            },
            controller: [
                'selectedCompany',
                '$scope',
                'Core',
                'CurrentUser',
                'Feature',
                function (selectedCompany, $scope, Core, CurrentUser, Feature) {
                    $scope.upgradeUrl = Core.upgradeUrl(
                        selectedCompany,
                        CurrentUser.profile,
                        Feature.hasFeature('not_activated'),
                    );
                },
            ],
        };
    }
})();
