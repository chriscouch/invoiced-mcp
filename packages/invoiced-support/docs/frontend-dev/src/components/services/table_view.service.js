/* globals moment */
(function () {
    'use strict';

    angular.module('app.components').factory('TableView', TableViewService);

    TableViewService.$inject = [
        '$location',
        '$state',
        '$q',
        '$modal',
        'Core',
        'localStorageService',
        'selectedCompany',
        'UiFilterService',
        'ColumnArrangementService',
        'LeavePageWarning',
        'Permission',
        'CustomField',
    ];

    function TableViewService(
        $location,
        $state,
        $q,
        $modal,
        Core,
        lss,
        selectedCompany,
        UiFilterService,
        ColumnArrangementService,
        LeavePageWarning,
        Permission,
        CustomField,
    ) {
        /*
            This generates the logic for rendering a table view for a result set
            that comes from the Invoiced API.

            Options:
            - modelType (required)
            - titleSingular (required)
            - titlePlural (required)
            - findAllMethod (required)
            - columns (required)
            - icon
            - defaultPerPage
            - addedFilterFields
            - savedFilters
            - defaultSort
            - buildRequest
            - transformResult
            - clickRow
            - hoverActions
            - titleMenu
            - actions
            - exportable
            - customFields
         */
        function TableView(config) {
            this.config = {
                modelType: '',
                titleSingular: 'Item',
                titlePlural: 'Items',
                defaultPerPage: 10,
                addedFilterFields: [],
                savedFilters: true,
                defaultSort: null,
                buildRequest: null,
                transformResult: null,
                findAllMethod: null,
                clickRow: null,
                hoverActions: [],
                columns: [],
                titleMenu: null,
                actions: [],
                exportable: false,
                customFields: false,
            };
            angular.extend(this.config, config);

            this.loading = false;
            this.page_count = 1;
            this.total_count = 0;
            this.filterFields = [];
            this.filter = {};
            this.filterStr = '';
            this.savedFilters = [];
            this._defaultFilter = {};
            this.sort = config.defaultSort;
            this.rows = [];
            this.clickableRows = typeof this.config.clickRow === 'function';
            this.columns = [];
            this.actions = [];
            this.hoverActions = [];
            this._currentStateName = $state.current.name;

            // Look for a previously selected per page
            let perPageRemembered = lss.get(this._currentStateName + '.per_page');
            if (perPageRemembered) {
                this.config.defaultPerPage = perPageRemembered;
            }
        }

        TableView.prototype.initialize = function () {
            const table = this;
            table.loading = true;

            // Set the page title
            Core.setTitle(table.config.titlePlural);

            // Generate the column and filter field definitions
            table._completeColumnDefinition().then(function () {
                table._loadColumns();
                table.generateFilterFields();
                table._generateActions();
                table._generateHoverActions();

                // recall the last used filter if
                // no other filtering was specified
                // in the URL / query parameters
                let hasQueryParameters = Object.keys($location.search()).length > 0;
                if (hasQueryParameters) {
                    table._pullQueryParameters();
                } else {
                    let savedFilter = table._recallFilter();
                    if (savedFilter) {
                        table.applyFilter(savedFilter);
                    } else {
                        table.applyFilter({});
                    }
                }
            });
        };

        TableView.prototype._pullQueryParameters = function () {
            // parse query parameters for filtering and pagination
            this.applyFilter(angular.copy($location.search()));
        };

        /* Filtering */

        // This should be called every time the filter field definition changes.
        TableView.prototype.generateFilterFields = function () {
            const table = this;
            table.filterFields = [];
            angular.forEach(table.config.columns, function (column) {
                if (typeof column.filterable !== 'undefined' && !column.filterable) {
                    return;
                }

                let filterField = {
                    id: column.id,
                    label: column.name,
                    type: column.type,
                };

                if (typeof column.displayInFilterString !== 'undefined') {
                    filterField.displayInFilterString = column.displayInFilterString;
                }
                if (typeof column.values !== 'undefined') {
                    filterField.values = column.values;
                }
                if (typeof column.serialize !== 'undefined') {
                    filterField.serialize = column.serialize;
                }
                if (typeof column.defaultValue !== 'undefined') {
                    filterField.defaultValue = column.defaultValue;
                }

                table.filterFields.push(filterField);
            });
            table.filterFields = table.filterFields.concat(table.config.addedFilterFields);
            table._defaultFilter = UiFilterService.getDefaultFilter(table.filterFields, table.config.defaultPerPage);
        };

        TableView.prototype.openFilter = function () {
            const table = this;
            const modalInstance = $modal.open({
                templateUrl: 'components/views/filter.html',
                controller: 'ListFilterController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                windowClass: 'modal-filter',
                animate: false,
                resolve: {
                    filter: () => table.filter,
                    filterFields: () => table.filterFields,
                },
            });

            modalInstance.result.then(
                function (filter) {
                    table.applyFilter(filter);
                },
                function () {
                    // canceled
                },
            );
        };

        TableView.prototype.addToFilter = function (addedFilter) {
            // Go back to the first page unless otherwise stated
            if (typeof addedFilter.page === 'undefined') {
                addedFilter.page = 1;
            }
            let filter = angular.copy(this.filter);
            angular.extend(filter, addedFilter);
            this.applyFilter(filter);
        };

        TableView.prototype.applyFilter = function (filter) {
            const table = this;

            // Convert legacy saved filters with metadata conditions.
            if (typeof filter.metadata === 'object') {
                angular.forEach(filter.metadata, function (value, key) {
                    filter['metadata.' + key] = {
                        operator: '=',
                        value: value,
                    };
                });
                delete filter.metadata;
            }

            for (let i in filter) {
                // ensure the page is an integer because
                // ngOptions will not recognize a string page number
                let value = filter[i];
                if (i === 'page') {
                    value = parseInt(value);
                }

                // Convert a legacy object (eg customer) to an ID.
                if (value && typeof value === 'object' && typeof value.id !== 'undefined') {
                    value = value.id;
                }

                if (typeof table._defaultFilter[i] === 'object') {
                    // JSON decode objects/arrays in the query parameters
                    if (typeof value === 'string' && (value[0] === '{' || value[0] === '[')) {
                        value = angular.fromJson(value);
                    }

                    // Convert simple filter to advanced filter format.
                    // This format is used by query parameters in URLs
                    // and legacy saved filters.
                    if (
                        typeof table._defaultFilter[i].operator !== 'undefined' &&
                        typeof table._defaultFilter[i].value !== 'undefined' &&
                        typeof value !== 'object' &&
                        value
                    ) {
                        value = {
                            operator: '=',
                            value: value,
                        };
                    }
                }

                filter[i] = value;
            }

            // remember the selection in local storage
            table._rememberFilter(filter);

            let completeFilter = angular.copy(table._defaultFilter);
            angular.extend(completeFilter, filter);
            table.filter = angular.copy(completeFilter);
            table._updateFilterString();

            table.findAll();
        };

        TableView.prototype.resetFilter = function () {
            // reset pagination
            this.applyFilter({
                per_page: this.filter.per_page,
                page: 1,
            });
        };

        TableView.prototype.toggleSort = function (column) {
            if (this.sort === column + ' ASC') {
                this.sort = column + ' DESC';
            } else {
                this.sort = column + ' ASC';
            }

            this.findAll();
        };

        TableView.prototype.goToPage = function (page, perPage) {
            // memorize the selection in local storage
            lss.add(this._currentStateName + '.per_page', perPage);

            this.addToFilter({
                page: page,
                per_page: perPage,
            });
        };

        TableView.prototype._rememberFilter = function (filter) {
            filter = angular.copy(filter);
            for (let i in filter) {
                let value = filter[i];
                if (value instanceof Date) {
                    filter[i] = moment(value).unix();
                }
            }

            lss.add(this._currentStateName + '.applied_filter.' + selectedCompany.id, angular.toJson(filter));
        };

        TableView.prototype._recallFilter = function () {
            let savedFilter = lss.get(this._currentStateName + '.applied_filter.' + selectedCompany.id);
            if (!savedFilter) {
                return null;
            }

            return angular.fromJson(savedFilter);
        };

        TableView.prototype._updateFilterString = function () {
            const table = this;
            table.filterStr = UiFilterService.buildFilterString(
                table.filter,
                table.filterFields,
                function (enrichedStr) {
                    table.filterStr = enrichedStr;
                },
            );
            table.usingFilter = !!table.filterStr;
        };

        /* Column Arrangement */

        TableView.prototype._completeColumnDefinition = function () {
            const table = this;
            angular.forEach(table.config.columns, function (column) {
                if (typeof column.type === 'undefined') {
                    column.type = 'string';
                }

                if (typeof column.currencyField === 'undefined') {
                    column.currencyField = 'currency';
                }
            });

            return $q(function (resolve) {
                if (table.config.customFields) {
                    table._loadCustomFields(resolve);
                } else {
                    resolve();
                }
            });
        };

        TableView.prototype._loadColumns = function () {
            this.columns = ColumnArrangementService.getSelectedColumns(this.config.modelType, this.config.columns);
        };

        TableView.prototype.openColumnSelector = function () {
            LeavePageWarning.block();

            const table = this;
            const modalInstance = $modal.open({
                templateUrl: 'ui/views/column-arrangement-modal.html',
                controller: 'ColumnArrangementController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    type: function () {
                        return table.config.modelType;
                    },
                    columns: function () {
                        return table.config.columns;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    LeavePageWarning.unblock();

                    if (LeavePageWarning.canLeave()) {
                        table._loadColumns();
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        TableView.prototype._loadCustomFields = function (resolve) {
            const table = this;

            CustomField.all(
                function (customFields) {
                    // Add each custom field to column definition
                    angular.forEach(customFields, function (customField) {
                        if (customField.object === table.config.modelType) {
                            table.config.columns.push({
                                id: 'metadata.' + customField.id,
                                name: customField.name,
                                type: customField.type,
                                sortable: false,
                            });
                        }

                        resolve();
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    resolve();
                },
            );
        };

        /* Data Loading / API Calls */

        TableView.prototype.findAll = function () {
            this.loading = true;

            const table = this;
            if (typeof table.config.buildRequest === 'function') {
                let result = table.config.buildRequest(this);
                if (typeof result.then === 'function') {
                    result.then(function (params) {
                        table._loadData(params);
                    });
                } else {
                    table._loadData(result);
                }
            } else {
                table._loadData({});
            }
        };

        TableView.prototype._loadData = function (params) {
            const table = this;
            let findAllParams = {
                page: table.filter.page,
                per_page: table.filter.per_page,
            };

            angular.forEach(params, function (value, key) {
                // encode structured objects
                if (typeof value === 'object') {
                    angular.forEach(value, function (value2, key2) {
                        findAllParams[key + '[' + key2 + ']'] = value2;
                    });
                    // encode boolean values
                } else if (typeof value === 'boolean') {
                    findAllParams[key] = value ? '1' : '0';
                } else if (value instanceof Array) {
                    findAllParams[key] = value.join(',');
                    // otherwise value directly maps
                } else {
                    findAllParams[key] = value;
                }
            });

            table.config.findAllMethod(
                findAllParams,
                function (result, headers) {
                    table.total_count = headers('X-Total-Count');

                    // compute page count from pagination links
                    const links = Core.parseLinkHeader(headers('Link'));
                    table.page_count = links.last.match(/[\?\&]page=(\d+)/)[1];

                    // if the requested page exceeds the page count then
                    // go back to the first page
                    if (findAllParams.page > table.page_count) {
                        table.addToFilter({});
                        return;
                    }

                    if (typeof table.config.transformResult === 'function') {
                        result = table.config.transformResult(result);
                    }

                    table.rows = result;
                    table.loading = false;
                },
                function (result) {
                    table.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        /* Actions */

        TableView.prototype._generateActions = function () {
            const table = this;
            table.actions = [];
            angular.forEach(this.config.actions, function (action) {
                let action2 = {
                    classes: '',
                    permissions: [],
                    show: true,
                };

                angular.extend(action2, action);

                if (typeof action.allPermissions !== 'undefined') {
                    action2.show = action2.show && Permission.hasAllPermissions(action.allPermissions);
                } else if (typeof action.somePermissions !== 'undefined') {
                    action2.show = action2.show && Permission.hasSomePermissions(action.somePermissions);
                }

                table.actions.push(action2);
            });
        };

        TableView.prototype.performAction = function (action) {
            if (typeof action.perform === 'function') {
                action.perform(this, action);
            }
        };

        /* Exports */

        TableView.prototype.export = function () {
            const table = this;
            const type = table.config.modelType;

            if (typeof table.config.buildRequest === 'function') {
                let result = table.config.buildRequest(this);
                if (typeof result.then === 'function') {
                    result.then(function (options) {
                        table._loadData(type, options);
                    });
                } else {
                    table._export(type, result);
                }
            } else {
                table._export(type, {});
            }
        };

        TableView.prototype._export = function (type, options) {
            $modal.open({
                templateUrl: 'exports/views/export.html',
                controller: 'ExportController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    type: function () {
                        return type;
                    },
                    options: function () {
                        return options;
                    },
                },
            });
        };

        /* Row Behavior */

        TableView.prototype.clickRow = function ($event, row) {
            if (!this.clickableRows) {
                return;
            }

            if (shouldSkipClick($event)) {
                return;
            }

            this.config.clickRow(row);
        };

        function shouldSkipClick($event) {
            let target = $($event.target);

            // Skip if this is the hover actions on the table row
            if (target.is('.hover-actions') || target.parents('.hover-actions').length > 0) {
                return true;
            }

            // Skip if the user clicked a link that is not href=""
            if (target.is('a[href!=""]') || target.parents('a[href!=""]').length > 0) {
                return true;
            }

            return false;
        }

        TableView.prototype._generateHoverActions = function () {
            const table = this;
            table.hoverActions = [];
            angular.forEach(table.config.hoverActions, function (hoverAction) {
                let hoverAction2 = {
                    classes: '',
                    permissions: [],
                    show: true,
                    showForRow: function () {
                        return true;
                    },
                };

                angular.extend(hoverAction2, hoverAction);

                if (typeof hoverAction.allPermissions !== 'undefined') {
                    hoverAction2.show = hoverAction2.show && Permission.hasAllPermissions(hoverAction.allPermissions);
                } else if (typeof hoverAction.somePermissions !== 'undefined') {
                    hoverAction2.show = hoverAction2.show && Permission.hasSomePermissions(hoverAction.somePermissions);
                }

                table.hoverActions.push(hoverAction2);
            });
        };

        TableView.prototype.performHoverAction = function (hoverAction, row) {
            if (typeof hoverAction.perform === 'function') {
                hoverAction.perform(row, this, hoverAction);
            }
        };

        return TableView;
    }
})();
