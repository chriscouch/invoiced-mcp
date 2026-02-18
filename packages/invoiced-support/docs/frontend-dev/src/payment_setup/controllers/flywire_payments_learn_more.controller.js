(function () {
    'use strict';

    angular.module('app.content').controller('FlywirePaymentsLearnMoreController', FlywirePaymentsLearnMoreController);

    FlywirePaymentsLearnMoreController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'onboardingUrl',
        'pricing',
    ];

    function FlywirePaymentsLearnMoreController($scope, $modalInstance, selectedCompany, onboardingUrl, pricing) {
        $scope.onboardingUrl = onboardingUrl;
        $scope.pricing = pricing;
        $scope.currency = selectedCompany.currency;

        $scope.close = function () {
            $modalInstance.close();
        };
    }
})();
