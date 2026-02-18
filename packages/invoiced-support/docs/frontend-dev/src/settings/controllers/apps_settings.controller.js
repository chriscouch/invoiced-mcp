(function () {
    'use strict';

    angular.module('app.settings').controller('AppsSettingsController', AppsSettingsController);

    AppsSettingsController.$inject = [
        '$scope',
        '$stateParams',
        '$modal',
        'Core',
        'Integration',
        'AppDirectory',
        'localStorageService',
    ];

    function AppsSettingsController(
        $scope,
        $stateParams,
        $modal,
        Core,
        Integration,
        AppDirectory,
        localStorageService,
    ) {
        $scope.availableApps = AppDirectory.all();
        $scope.filteredApps = [];
        $scope.tab = $stateParams.tab;

        $scope.showAll = showAll;
        $scope.showInstalled = showInstalled;

        Core.setTitle('Apps');

        load();

        function load() {
            $scope.loading = true;

            Integration.findAll(
                { paginate: 'none' },
                function (integrations) {
                    $scope.loading = false;
                    $scope.integrations = integrations;
                    processIntegrations();

                    if ($stateParams.tab === 'all') {
                        showAll();
                    } else if ($stateParams.tab === 'installed') {
                        showInstalled();
                    } else if (localStorageService.get('appsTab') === 'all') {
                        showAll();
                    } else {
                        // Show installed tab by default unless there are none installed
                        showInstalled();
                        if ($scope.filteredApps.length === 0) {
                            showAll();
                        }
                    }
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function processIntegrations() {
            angular.forEach($scope.availableApps, function (app) {
                let loadedIntegration = $scope.integrations[app.id];
                app.connected = loadedIntegration ? loadedIntegration.connected : false;
                app.hidden = app.id === 'chartmogul' && !app.connected;
            });
        }

        function showAll() {
            $scope.tab = 'all';
            if (!$stateParams.tab) {
                localStorageService.add('appsTab', 'all');
            }
            filterApps(false);
        }

        function showInstalled() {
            $scope.tab = 'installed';
            if (!$stateParams.tab) {
                localStorageService.add('appsTab', 'installed');
            }
            filterApps(true);
        }

        function filterApps(connectedOnly) {
            $scope.filteredApps = [];
            angular.forEach($scope.availableApps, function (app) {
                if (!app.hidden && (!connectedOnly || app.connected)) {
                    $scope.filteredApps.push(app);
                }
            });
        }
    }
})();
