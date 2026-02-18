(function () {
    'use strict';

    angular.module('app.integrations').controller('ChartMogulSettingsController', ChartMogulSettingsController);

    ChartMogulSettingsController.$inject = ['$scope', 'Integration', 'Core'];

    function ChartMogulSettingsController($scope, Integration, Core) {
        $scope.save = save;

        function save(enabled) {
            $scope.saving = true;
            $scope.error = null;

            Integration.connect(
                {
                    id: 'chartmogul',
                },
                {
                    enabled: enabled,
                },
                function () {
                    $scope.saving = false;
                    Core.flashMessage('Your ChartMogul settings have been saved', 'success');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
