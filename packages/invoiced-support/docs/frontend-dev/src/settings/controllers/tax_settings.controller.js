(function () {
    'use strict';

    angular.module('app.settings').controller('TaxSettingsController', TaxSettingsController);

    TaxSettingsController.$inject = ['$scope', 'Settings'];

    function TaxSettingsController($scope, Settings) {
        loadSettings();

        function loadSettings() {
            $scope.loading = true;
            Settings.accountsReceivable(function (settings) {
                $scope.taxCalculator = settings.tax_calculator;
                $scope.loading = false;
            });
        }
    }
})();
