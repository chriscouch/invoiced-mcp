/* globals inflection, moment */
(function () {
    'use strict';

    angular.module('app.imports').controller('ViewImportController', ViewImportController);

    ViewImportController.$inject = [
        '$scope',
        '$rootScope',
        '$state',
        '$controller',
        '$timeout',
        'Import',
        'Core',
        'BrowsingHistory',
        'ObjectDeepLink',
    ];

    function ViewImportController(
        $scope,
        $rootScope,
        $state,
        $controller,
        $timeout,
        Import,
        Core,
        BrowsingHistory,
        ObjectDeepLink,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Import;
        $scope.modelTitleSingular = 'Import';

        $scope.progress = 0;
        $scope.show = {};
        $scope.importedObjects = [];

        //
        // Methods
        //

        let reload;
        $scope.postFind = function (_import) {
            $scope.import = _import;
            $scope.loadedAtLeastOnce = true;

            $rootScope.modelTitle = _import.name;
            Core.setTitle($scope.modelTitle + ' Import');

            $scope.type = inflection.titleize(_import.type);
            $scope.pluralType = inflection.pluralize(inflection.titleize(_import.type)).toLowerCase();

            // calculate progress
            $scope.progress = 0;
            if (_import.position > 0 && _import.total_records > 0) {
                $scope.progress = Math.min(100, Math.round((_import.position / _import.total_records) * 100));
            }

            // reload in 5s if the import has not finished yet
            let delta;
            if (_import.status == 'pending') {
                reload = $timeout(function () {
                    $scope.find($scope.modelId);
                }, 5000);

                // calculate run time
                delta = moment.unix(_import.created_at).diff(moment());
                $scope.duration = moment.duration(delta).humanize();
            } else {
                // calculate run time
                delta = moment.unix(_import.created_at).diff(moment.unix(_import.updated_at));
                $scope.duration = moment.duration(delta).humanize();
            }

            Import.importedObjects(
                {
                    id: _import.id,
                },
                function (objects) {
                    $scope.importedObjects = objects;
                },
            );

            BrowsingHistory.push({
                id: _import.id,
                type: 'import',
                title: _import.name,
            });
        };

        $scope.link = function (obj) {
            if ('contact' === obj.object && obj.customer_id) {
                ObjectDeepLink.goTo('customer', obj.customer_id);

                return;
            }

            ObjectDeepLink.goTo(obj.object, obj.id);
        };

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Import');

        // cancel any reload promises after leaving the page
        $scope.$on('$stateChangeStart', function () {
            if (reload) {
                $timeout.cancel(reload);
            }
        });
    }
})();
