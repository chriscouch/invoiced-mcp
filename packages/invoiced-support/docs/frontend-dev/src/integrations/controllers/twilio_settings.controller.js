(function () {
    'use strict';

    angular.module('app.integrations').controller('TwilioSettingsController', TwilioSettingsController);

    TwilioSettingsController.$inject = ['$scope', 'Integration', 'Core'];

    function TwilioSettingsController($scope, Integration, Core) {
        $scope.numbers = [];
        $scope.save = save;

        load();

        function load() {
            $scope.loading = true;
            $scope.error = null;

            Integration.settings(
                {
                    id: 'twilio',
                },
                function (result) {
                    $scope.loading = false;
                    $scope.numbers = result.phone_numbers;
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data;
                },
            );
        }

        function save(fromNumber) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                from_number: fromNumber,
            };

            Integration.connect(
                {
                    id: 'twilio',
                },
                params,
                function () {
                    $scope.saving = false;
                    Core.flashMessage('Your Twilio settings have been saved', 'success');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
