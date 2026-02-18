(function () {
    'use strict';

    angular.module('app.imports').controller('NewSpreadsheetImportController', NewSpreadsheetImportController);

    NewSpreadsheetImportController.$inject = [
        '$scope',
        '$stateParams',
        '$state',
        '$translate',
        'Import',
        'Core',
        'LeavePageWarning',
        'Importer',
        'CustomField',
        'Metadata',
    ];

    function NewSpreadsheetImportController(
        $scope,
        $stateParams,
        $state,
        $translate,
        Import,
        Core,
        LeavePageWarning,
        Importer,
        CustomField,
        Metadata,
    ) {
        //
        // Settings
        //

        $scope.importers = [];

        //
        // Presets
        //

        $scope.step = 1;
        $scope.options = {
            skipFirst: true,
            operation: 'create',
        };
        $scope.supportedOperations = [];

        //
        // Methods
        //

        $scope.goImport = function (id) {
            $state.go('manage.import.view', {
                id: id,
            });
        };

        $scope.step1 = function (input) {
            $scope.input = input;
            $scope.step = 1;
        };

        $scope.step2 = function (input, options) {
            $scope.step = 2;

            // parse records
            $scope.columns = [];
            $scope.records = [];
            $scope.notAllColumnsMatched = false;

            let data = input.split('\n');
            let numCols = 0;
            for (let i in data) {
                let row = data[i].split('\t');

                // attempt to parse the column names
                if (i == '0') {
                    if (options.skipFirst) {
                        $scope.columns = Importer.parseFirstRow(row, $scope.selected);
                        numCols = $scope.columns.length;

                        // check if all of the columns have been matched
                        $scope.notAllColumnsMatched = !Importer.matched($scope.columns);

                        // don't create a record from first row
                        continue;
                    } else {
                        // create an empty column for each column in the first row
                        numCols = row.length;
                        for (i = 0; i < numCols; i++) {
                            $scope.columns.push({
                                id: '',
                            });
                        }

                        $scope.notAllColumnsMatched = true;
                    }
                }

                // ensure record has the right # of columns
                if (row.length < numCols) {
                    while (row.length < numCols) {
                        row.push('');
                    }
                }

                $scope.records.push(row);
            }

            $scope.input = input;
            $scope.options = options;
        };

        $scope.matchedCol = function () {
            // check if all of the columns have been matched
            $scope.notAllColumnsMatched = !Importer.matched($scope.columns);
        };

        $scope.startImport = function (columns, records) {
            let params = {
                type: $scope.selected.type,
                options: {
                    operation: $scope.options.operation,
                },
            };

            // parse selected columns
            let parsed = Importer.parseColumns(columns);

            // validate no duplicate columns were shown
            let duplicates = Importer.findDuplicates(parsed.mapping);
            if (duplicates.length > 0) {
                $scope.error = {
                    message:
                        'The "' +
                        duplicates[0] +
                        '" field was mapped to more than one column. Each field can only be mapped to a single column. Please fix this before starting the import.',
                };
                return;
            }

            params.mapping = parsed.mapping;

            // remove all skipped columns from import
            params.lines = Importer.removeSkippedColumns(records, parsed.skip);

            $scope.importing = true;
            Import.create(
                params,
                function (_import) {
                    $scope.importing = false;
                    // unblock each form
                    LeavePageWarning.unblock();
                    LeavePageWarning.unblock();
                    $scope.goImport(_import.id);
                },
                function (result) {
                    $scope.importing = false;
                    $scope.error = result.data;
                },
            );
        };

        //
        // Initialization
        //

        loadImportFields();
        LeavePageWarning.watchForm($scope, 'importForm');
        LeavePageWarning.watchForm($scope, 'importForm2');

        function selectType(type) {
            angular.forEach($scope.importers, function (model) {
                if (model.type === type) {
                    $scope.selected = angular.copy(model);
                    $scope.selected.properties.splice(0, 0, {
                        id: 'skip',
                        name: '-- Skip',
                    });
                    $scope.selected.properties.splice(0, 0, {
                        id: '_operation',
                        name: 'Operation',
                        aliases: ['operation'],
                    });
                    $scope.selected.templateUrl = '/files/' + type.replace(/_/g, '-') + 's-import-template.xlsx';

                    $scope.supportedOperations = [];
                    angular.forEach(model.operations, function (operation) {
                        $scope.supportedOperations.push({
                            id: operation,
                            name: $translate.instant('imports.operations.' + operation),
                        });
                    });
                    $scope.options.operation = $scope.supportedOperations[0].id;

                    Core.setTitle('Import ' + model.name);
                }
            });
        }

        function loadImportFields() {
            Metadata.importFields(
                function (_config) {
                    $scope.importers = angular.copy(_config.fields);
                    selectType($stateParams.type);
                    loadCustomFields();
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadCustomFields() {
            CustomField.all(
                function (customFields) {
                    // add custom fields to supported importers
                    angular.forEach(customFields, function (customField) {
                        if ($scope.selected.customFieldType && customField.object === $scope.selected.customFieldType) {
                            $scope.selected.properties.push({
                                id: 'metadata.' + customField.id,
                                name: customField.name,
                            });
                        }

                        // line item custom fields
                        if ($scope.selected.hasLineItemCustomFields && customField.object === 'line_item') {
                            $scope.selected.properties.push({
                                id: 'line_item_metadata.' + customField.id,
                                name: customField.name,
                            });
                        }
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
