(function () {
    'use strict';

    angular.module('app.imports').controller('StartImportController', StartImportController);

    StartImportController.$inject = ['$scope', '$state', 'Core', 'Metadata', 'title'];

    function StartImportController($scope, $state, Core, Metadata, title) {
        $scope.go = function (importer) {
            if (importer.type === 'payment') {
                $state.go('manage.imports.start.payment');
            } else {
                $state.go('manage.imports.new.spreadsheet', { type: importer.type });
            }
        };

        Core.setTitle(title);
        loadImportFields();

        function loadImportFields() {
            $scope.importers = [];
            Metadata.importFields(
                function (_config) {
                    $scope.importers = angular.copy(_config.fields);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
