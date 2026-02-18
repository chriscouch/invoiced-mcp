(function () {
    'use strict';

    angular
        .module('app.settings')
        .controller('SubscriptionBillingSettingsController', SubscriptionBillingSettingsController);

    SubscriptionBillingSettingsController.$inject = [
        '$scope',
        '$modal',
        'Company',
        'selectedCompany',
        'Core',
        'Settings',
    ];

    function SubscriptionBillingSettingsController($scope, $modal, Company, selectedCompany, Core, Settings) {
        $scope.company = angular.copy(selectedCompany);

        $scope.saveSetting = saveSetting;

        Core.setTitle('Subscription Billing Settings');

        loadSettings();

        function loadSettings() {
            $scope.loadingSettings = true;

            Settings.subscriptionBilling(function (settings) {
                $scope.settings = settings;
                $scope.loadingSettings = false;
            });
        }

        function saveSetting(setting, value) {
            $scope.saving = true;
            $scope.error = null;

            let params = {};
            params[setting] = value;

            Settings.editSubscriptionBilling(
                params,
                function (settings) {
                    $scope.saving = false;

                    Core.flashMessage('Your settings have been updated.', 'success');

                    angular.extend($scope.settings, settings);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
