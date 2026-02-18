(function () {
    'use strict';

    angular.module('app.integrations').controller('AvalaraSettingsController', AvalaraSettingsController);

    AvalaraSettingsController.$inject = ['$scope', 'Integration', 'Core'];

    function AvalaraSettingsController($scope, Integration, Core) {
        $scope.save = save;

        function save(commitMode) {
            $scope.saving = true;
            $scope.error = null;

            Integration.connect(
                {
                    id: 'avalara',
                },
                {
                    commit_mode: commitMode,
                },
                function () {
                    $scope.saving = false;
                    Core.flashMessage('Your Avalara settings have been saved', 'success');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
