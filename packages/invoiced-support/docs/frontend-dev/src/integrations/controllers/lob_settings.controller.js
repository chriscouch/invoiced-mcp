(function () {
    'use strict';

    angular.module('app.integrations').controller('LobSettingsController', LobSettingsController);

    LobSettingsController.$inject = ['$scope', 'Integration', 'Core'];

    function LobSettingsController($scope, Integration, Core) {
        $scope.save = save;

        function save(returnEnvelopes, useColor, customEnvelope) {
            $scope.saving = true;
            $scope.error = null;

            Integration.connect(
                {
                    id: 'lob',
                },
                {
                    return_envelopes: returnEnvelopes,
                    use_color: useColor,
                    custom_envelope: customEnvelope,
                },
                function () {
                    $scope.saving = false;
                    Core.flashMessage('Your Lob settings have been saved', 'success');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
