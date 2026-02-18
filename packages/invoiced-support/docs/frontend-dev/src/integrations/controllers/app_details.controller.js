/* globals vex */
(function () {
    'use strict';

    angular.module('app.integrations').controller('AppDetailsController', AppDetailsController);

    AppDetailsController.$inject = ['$scope', '$stateParams', '$state', 'Integration', 'Core', 'AppDirectory'];

    function AppDetailsController($scope, $stateParams, $state, Integration, Core, AppDirectory) {
        $scope.integrationDefinition = AppDirectory.get($stateParams.id);
        if (!$scope.integrationDefinition) {
            $state.go('manage.settings.apps');
            return;
        }

        $scope.integration = { connected: false };
        $scope.tab = 'overview';
        $scope.loading = 0;

        $scope.changeTab = changeTab;
        $scope.disconnect = disconnect;
        $scope.installedQuickBooksDesktop = installedQuickBooksDesktop;

        Core.setTitle($scope.integrationDefinition.name);

        load();

        function load() {
            $scope.loading++;

            Integration.retrieve(
                { id: $scope.integrationDefinition.id },
                function (integration) {
                    $scope.loading--;
                    $scope.integration = integration;
                },
                function () {
                    // do nothing on error
                    $scope.loading--;
                },
            );
        }

        function changeTab(tab) {
            $scope.tab = tab;
        }

        function disconnect() {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this app?',
                callback: function (result) {
                    if (result) {
                        $scope.disconnecting = false;

                        Integration.disconnect(
                            {
                                id: $scope.integrationDefinition.id,
                            },
                            function () {
                                $scope.disconnecting = false;
                                $state.go('manage.settings.apps');
                                Core.flashMessage('This app has been deleted', 'success');
                            },
                            function (result) {
                                $scope.disconnecting = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        }

        function installedQuickBooksDesktop() {
            load();
            $state.go('manage.settings.app.configuration', { id: 'quickbooks_desktop' });
        }
    }
})();
