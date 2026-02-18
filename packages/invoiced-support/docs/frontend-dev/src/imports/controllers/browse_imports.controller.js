(function () {
    'use strict';

    angular.module('app.imports').controller('BrowseImportsController', BrowseImportsController);

    BrowseImportsController.$inject = ['$scope', '$state', '$controller', 'Import', 'Core', 'UiFilterService'];

    function BrowseImportsController($scope, $state, $controller, Import, Core, UiFilterService) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Import;
        $scope.modelTitleSingular = 'Import';
        $scope.modelTitlePlural = 'Imports';

        $scope.imports = [];

        //
        // Methods
        //

        $scope.preFindAll = function () {
            return buildFindParams($scope.filter);
        };

        $scope.postFindAll = function (imports) {
            $scope.imports = imports;
        };

        $scope.filterFields = function () {
            return [
                {
                    id: 'status',
                    label: 'Status',
                    type: 'enum',
                    values: [
                        { text: 'Succeeded', value: 'succeeded' },
                        { text: 'Pending', value: 'pending' },
                        { text: 'Failed', value: 'failed' },
                    ],
                },
                {
                    id: 'num_imported',
                    label: '# Created',
                    type: 'number',
                },
                {
                    id: 'num_updated',
                    label: '# Updated',
                    type: 'number',
                },
                {
                    id: 'num_failed',
                    label: '# Failed',
                    type: 'number',
                },
                {
                    id: 'total_records',
                    label: 'Total Records',
                    type: 'number',
                },
                {
                    id: 'created_at',
                    label: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'name',
                    label: 'Name',
                    type: 'string',
                },
                {
                    id: 'user',
                    label: 'User',
                    type: 'user',
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'id DESC',
                    values: [
                        { value: 'id ASC', text: 'Date, Oldest First' },
                        { value: 'id DESC', text: 'Date, Newest First' },
                    ],
                },
            ];
        };

        $scope.noResults = function () {
            return $scope.imports.length === 0;
        };

        $scope.goImport = function (id) {
            $state.go('manage.import.view', {
                id: id,
            });
        };

        //
        // Initialization
        //

        $scope.initializeListPage();
        Core.setTitle('Imports');

        function buildFindParams(input) {
            return {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter(input, $scope._filterFields),
                sort: input.sort,
            };
        }
    }
})();
