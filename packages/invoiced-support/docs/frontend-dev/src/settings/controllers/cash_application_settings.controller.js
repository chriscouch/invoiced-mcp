(function () {
    'use strict';

    angular.module('app.settings').controller('CashApplicationSettingsController', CashApplicationSettingsController);

    CashApplicationSettingsController.$inject = ['$scope', 'selectedCompany', 'Core', 'Integration', 'Settings'];

    function CashApplicationSettingsController($scope, selectedCompany, Core, Integration, Settings) {
        $scope.company = angular.copy(selectedCompany);
        $scope.editShortPay = false;

        $scope.saveShortPaySettings = function (shortPay) {
            $scope.saving = true;

            shortPay.units = $scope.isPercent ? 'percent' : 'dollars';

            Settings.editCashApplication(
                {
                    short_pay_units: shortPay.units,
                    short_pay_amount: shortPay.amount,
                },
                function () {
                    Core.flashMessage('Your settings have been updated.', 'success');

                    $scope.saving = false;
                    $scope.editShortPay = false;
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.cancelEditShortPay = function () {
            $scope.editShortPay = false;
            loadSettings();
        };

        function loadSettings() {
            $scope.loadingSettings = true;

            Settings.cashApplication(function (settings) {
                $scope.shortPay = {
                    units: settings.short_pay_units,
                    amount: settings.short_pay_amount,
                };

                $scope.isPercent = settings.short_pay_units === 'percent';
            });
        }

        function loadEarthClassMailIntegration() {
            $scope.loading = true;

            Integration.retrieve(
                {
                    id: 'earth_class_mail',
                },
                function (integration) {
                    $scope.loading = false;
                    $scope.integration = integration;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        //
        // Initialization
        //

        Core.setTitle('Cash Application');

        loadSettings();
        loadEarthClassMailIntegration();
    }
})();
