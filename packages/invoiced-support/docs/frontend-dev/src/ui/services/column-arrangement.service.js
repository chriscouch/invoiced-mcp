(function () {
    'use strict';

    angular.module('app.core').factory('ColumnArrangementService', ColumnArrangementService);

    ColumnArrangementService.$inject = [
        '$modal',
        '$translate',
        'Core',
        'InvoicedConfig',
        'Feature',
        'localStorageService',
        'selectedCompany',
    ];

    function ColumnArrangementService($modal, $translate, Core, InvoicedConfig, Feature, lss, selectedCompany) {
        return {
            getSelectedColumns: getSelectedColumns,
            getColumnsFromConfig: getColumnsFromConfig,
            save: rememberColumns,
        };

        function getSelectedColumns(type, allColumns) {
            migrateLegacyStorageFormat(type);

            let columnIds = getRememberedColumns(type);
            let columns = [];
            if (columnIds) {
                // Convert from column IDs into the full objects
                angular.forEach(columnIds, function (column) {
                    angular.forEach(allColumns, function (fullColumn) {
                        if (column.id === fullColumn.id) {
                            columns.push(fullColumn);
                            return false;
                        }
                    });
                });
            } else {
                columns = getDefaultColumns(type, allColumns);
            }

            return translateDecorator(sortingDecorator(columns));
        }

        function getColumnsFromConfig(type) {
            let columns = [];
            if (typeof InvoicedConfig.column_arrangement[type] !== 'undefined') {
                columns = InvoicedConfig.column_arrangement[type];
            }

            if (type === 'invoice') {
                // NOTE:
                // slice(0) is called on the column arrangement to create a shallow copy
                // of the array before pushing the 'chase' column. This is to prevent
                // the 'chase' column from being visible if a user were to switch from
                // a company with legacy_chasing enabled to one with legacy_chasing disabled.
                // I.e. InvoicedConfig is shared between tenants and should not be modified.
                columns = columns.slice(0);
                if (Feature.hasFeature('legacy_chasing')) {
                    columns.push({
                        id: 'chase',
                        name: 'Chase',
                        type: 'boolean',
                    });
                }
            }

            return translateDecorator(columns);
        }

        function rememberColumns(type, data) {
            if (data.length === 0) {
                Core.showMessage('You should select at least one column', 'error');
                return false;
            }

            const result = data.map(column => {
                return { id: column.id };
            });
            lss.set(buildStorageKey(type), result);

            return true;
        }

        function translateDecorator(columns) {
            return columns.map(function (column) {
                column.name = hasTranslation(column.name) ? $translate.instant('ui.columns.' + column.id) : column.name;
                return column;
            });
        }

        function hasTranslation(column) {
            let translation = $translate.instant(column);
            return translation !== column && translation !== '';
        }

        function sortingDecorator(columns) {
            return columns.map(function (column) {
                column.sortIndex = column.sortId || column.id;
                return column;
            });
        }

        function getRememberedColumns(type) {
            return angular.fromJson(lss.get(buildStorageKey(type))) || null;
        }

        function getDefaultColumns(type, allColumns) {
            allColumns = angular.copy(allColumns);
            return getDefaultSorted(allColumns);
        }

        function getDefaultSorted(column) {
            return column.filter(getDefaults).sort(sortDefaults);
        }

        function getDefaults(column) {
            return column.default === true;
        }

        function sortDefaults(a, b) {
            a = a.defaultOrder || 999;
            b = b.defaultOrder || 999;
            return a - b;
        }

        function buildStorageKey(type) {
            return 'column_arrangement.' + selectedCompany.id + '.' + type;
        }

        // Converts the legacy column format into the newer format.
        function migrateLegacyStorageFormat(type) {
            const storageKey = 'columnsArrangement.' + selectedCompany.id;
            const storedColumns = angular.fromJson(lss.get(storageKey)) || null;
            const key = type + '_fields';
            if (!storedColumns || typeof storedColumns[key] === 'undefined') {
                return;
            }

            rememberColumns(type, storedColumns[key]);

            delete storedColumns[key];
            lss.set(storageKey, storedColumns);
        }
    }
})();
