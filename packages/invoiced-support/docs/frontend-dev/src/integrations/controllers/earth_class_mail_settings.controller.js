(function () {
    'use strict';

    angular.module('app.integrations').controller('EarthClassMailSettingsController', EarthClassMailSettingsController);

    EarthClassMailSettingsController.$inject = ['$scope', '$stateParams', '$state', 'Integration', 'Core'];

    function EarthClassMailSettingsController($scope, $stateParams, $state, Integration, Core) {
        $scope.sync = sync;
        $scope.save = save;

        $scope.$watch('integration', load);

        function load(integration) {
            if (!integration) {
                return;
            }

            $scope.loading = true;
            $scope.error = null;

            Integration.earthClassMailInboxes(
                {
                    account_id: integration.extra.account_id,
                },
                function (result) {
                    $scope.loading = false;
                    $scope.inboxes = result;
                    angular.forEach(result, function (inbox) {
                        if (inbox.id === integration.extra.inbox_id) {
                            $scope.inbox = inbox;
                        }
                    });
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data;
                },
            );
        }

        function sync() {
            $scope.synced = true;
            Integration.enqueueSync(
                {
                    id: 'earth_class_mail',
                },
                function () {
                    Core.flashMessage('Your sync has been initiated.', 'success');
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function save(inboxId) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                inbox_id: inboxId,
            };

            Integration.connect(
                {
                    id: 'earth_class_mail',
                },
                params,
                function () {
                    $scope.saving = false;
                    Core.flashMessage('Your Earth Class Mail settings have been saved', 'success');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
