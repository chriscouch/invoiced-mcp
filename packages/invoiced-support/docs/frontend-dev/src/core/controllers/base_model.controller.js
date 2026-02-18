/* globals _, moment, vex */
(function () {
    'use strict';

    angular.module('app.core').controller('BaseModelController', BaseModelController);

    BaseModelController.$inject = [
        '$rootScope',
        '$scope',
        '$stateParams',
        '$location',
        '$state',
        '$modal',
        '$filter',
        '$q',
        'localStorageService',
        'LeavePageWarning',
        'CurrentUser',
        'InvoiceTemplate',
        'Core',
        'selectedCompany',
        'Feature',
        'CustomField',
        'ObjectDeepLink',
        'UiFilterService',
    ];

    function BaseModelController(
        $rootScope,
        $scope,
        $stateParams,
        $location,
        $state,
        $modal,
        $filter,
        $q,
        lss,
        LeavePageWarning,
        CurrentUser,
        InvoiceTemplate,
        Core,
        selectedCompany,
        Feature,
        CustomField,
        ObjectDeepLink,
        UiFilterService,
    ) {
        $scope.company = selectedCompany;
        $scope.currentUser = CurrentUser.profile;
        $scope.savedFilters = [];
        $scope.allColumns = [];

        let escapeHtml = $filter('escapeHtml');
        let defaultPerPage = lss.get($state.current.name + '.per_page') || 10;
        let defaultFilter;

        angular.extend($scope, {
            modelTitleSingular: '',
            modelTitlePlural: '',
            modelListName: '',
            page_count: 1,
            total_count: 0,
            deleteKeysForDuplicate: [],
            deleteMetadataKeysForDuplicate: [],
            loaded: {},
            filter: {},
        });

        $rootScope.modelTitle = false;

        let modelRoute = $location.path().split('/')[1];
        $scope.metadataLoaded = false;

        $scope.tableHasMetadata = function (columns) {
            return $scope.tableHasColumn(columns, 'metadata');
        };

        $scope.tableHasColumn = function (columns, id) {
            return (
                columns.find(function (item) {
                    return item.id.indexOf(id) === 0;
                }) !== undefined
            );
        };

        let state = $state.current.name.split('.');
        $scope.action = state[2];
        if ($scope.action.indexOf('new') === 0) {
            $scope.action = 'new';
        }

        $scope.$on('$routeUpdate', function () {
            // update parameters manually because reloadOnSearch = false
            pullQueryParameters();
        });

        //
        // Methods
        //

        $scope.initializeListPage = function () {
            if (!$scope.modelListName) {
                $scope.modelListName = $scope.modelTitlePlural.toLowerCase();
            }

            // generate the filter field definition before any filtering methods are called
            $scope.generateFilterFields();

            // recall the last used filter if
            // no other filtering was specified
            // in the URL / query parameters
            let hasQueryParameters = Object.keys($location.search()).length > 0;
            if (hasQueryParameters) {
                pullQueryParameters();
            } else {
                let savedFilter = recallFilter();
                if (savedFilter) {
                    applyFilter(savedFilter);
                } else {
                    applyFilter({});
                }
            }
        };

        $scope.initializeDetailPage = function () {
            if (!$scope.modelListName) {
                $scope.modelListName = $scope.modelTitlePlural.toLowerCase();
            }

            // Any page with an existing model
            if (typeof $stateParams.id !== 'undefined') {
                $scope.find($stateParams.id);
                $scope.modelId = $stateParams.id;
            }
        };

        $scope.initializeEditPage = function () {
            if (!$scope.modelListName) {
                $scope.modelListName = $scope.modelTitlePlural.toLowerCase();
            }

            // prevent the user from leaving without a warning
            LeavePageWarning.watchForm($scope, 'modelForm');

            // Any page with an existing model
            if (typeof $stateParams.id !== 'undefined') {
                $scope.find($stateParams.id);
                $scope.modelId = $stateParams.id;
            }
        };

        /** @abstract **/
        $scope.loadSettings = function () {};

        $scope.applyColumnArrangement = function () {
            $scope.loadSettings();
            if (!$scope.metadataLoaded) {
                let loadMetadata = $scope.tableHasMetadata($scope.columns);
                if (loadMetadata !== undefined) {
                    $scope.findAll();
                    $scope.metadataLoaded = true;
                }
            }
        };

        $scope.loadCustomFields = function (type, updateFilter) {
            return $q(function (resolve) {
                CustomField.all(
                    function (customFields) {
                        $scope.customFields = [];
                        angular.forEach(customFields, function (customField) {
                            if (customField.object === type) {
                                $scope.customFields.push(customField);

                                $scope.allColumns.push({
                                    id: 'metadata.' + customField.id,
                                    name: customField.name,
                                    type: customField.type,
                                    sortable: false,
                                });
                            }
                        });

                        // update filter definition and rebuild the filter string
                        if (updateFilter) {
                            $scope.generateFilterFields();
                            $scope.updateFilterString();
                        }
                        $scope.loadSettings();
                        resolve();
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                        resolve();
                    },
                );
            });
        };

        /* Filtering */

        // This should be called every time the filter field definition changes.
        $scope.generateFilterFields = function () {
            $scope._filterFields = $scope.filterFields();
            defaultFilter = UiFilterService.getDefaultFilter($scope._filterFields, defaultPerPage);
        };

        $scope.openFilter = function () {
            const modalInstance = $modal.open({
                templateUrl: 'components/views/filter.html',
                controller: 'ListFilterController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                windowClass: 'modal-filter',
                animate: false,
                resolve: {
                    filter: function () {
                        return $scope.filter;
                    },
                    filterFields: $scope.filterFields,
                },
            });

            modalInstance.result.then($scope.applyFilter, function () {
                // canceled
            });
        };

        // applies a new filter on top of the default
        $scope.applyFilter = applyFilter;

        // apply given filter params overlaid onto current filter with pagination reset
        $scope.addToFilter = function (filter) {
            filter.page = 1;
            addToFilter(filter);
        };

        $scope.updateFilterString = function () {
            $scope.filterStr = UiFilterService.buildFilterString(
                $scope.filter,
                $scope._filterFields,
                function (enrichedStr) {
                    $scope.filterStr = enrichedStr;
                },
            );
            $scope.usingFilter = !!$scope.filterStr;
        };

        $scope.resetFilter = function () {
            // reset pagination
            applyFilter({
                per_page: $scope.filter.per_page,
                page: 1,
            });
        };

        $scope.filterFields = function () {
            return [];
        };

        $scope.toggleSort = function (column) {
            let filter = { page: 1 };
            if ($scope.filter.sort === column + ' ASC') {
                filter.sort = column + ' DESC';
            } else {
                filter.sort = column + ' ASC';
            }
            addToFilter(filter);
        };

        $scope.goToPage = function (page, perPage) {
            // memorize the selection in local storage
            lss.add($state.current.name + '.per_page', perPage);

            addToFilter({
                page: page,
                per_page: perPage,
            });
        };

        /* API Operations */

        $scope.findAll = function () {
            $scope.loading = true;

            if (typeof $scope.preFindAll === 'function') {
                let result = $scope.preFindAll();
                if (typeof result.then === 'function') {
                    result.then(function (params) {
                        _findAll(params);
                    });
                } else {
                    _findAll(result);
                }
            } else {
                _findAll({});
            }
        };

        $scope.find = function (id, cb) {
            if (!id) {
                $location.path('/' + modelRoute);
                return;
            }

            $scope.loading = true;

            let findParams = {
                id: id,
            };

            if (typeof $scope.preFind == 'function') {
                $scope.preFind(findParams);
            }

            $scope.model.find(
                findParams,
                function (result) {
                    let model = $scope.postFind(result);

                    if ($scope.action === 'duplicate') {
                        duplicate(model);
                    }

                    if (cb) {
                        cb(model);
                    }

                    $scope.loading = false;
                },
                function (error) {
                    $scope.loading = false;

                    if (error.status == 404) {
                        $location.path('/' + modelRoute);
                    }
                },
            );
        };

        $scope.save = function (input, isEdit) {
            if (typeof isEdit === 'undefined') {
                isEdit = $scope.action === 'edit';
            }

            let params = angular.copy(input);

            if (typeof $scope.preSave == 'function') {
                $scope.preSave(params, function (params) {
                    _save(params, isEdit);
                });
            } else {
                _save(params, isEdit);
            }
        };

        $scope.delete = function (model) {
            vex.dialog.confirm({
                message: $scope.deleteMessage(model),
                callback: function (result) {
                    if (result) {
                        _delete(model);
                    }
                },
            });
        };

        $scope.postDelete = function (model) {
            if ($scope.action === 'browse') {
                // remove the model from the list
                let k = $scope.modelListName;
                for (let i in $scope[k]) {
                    if ($scope[k][i].id == model.id) {
                        $scope[k].splice(i, 1);
                        break;
                    }
                }

                if ($scope[k].length === 0) {
                    $scope.findAll();
                }
            } else {
                $location.path('/' + modelRoute);
            }
        };

        /* Object Links */

        $scope.goToObject = function ($event, type, id) {
            if (shouldSkipClick($event)) {
                return;
            }

            ObjectDeepLink.goTo(type, id);
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

        /* Templates */

        $scope.loadTemplates = function (cb) {
            if (!Feature.hasFeature('invoice_templates')) {
                return;
            }

            InvoiceTemplate.templates(function (templates) {
                $scope.templates = _.toArray(templates);

                if (cb) {
                    cb(templates);
                }
            });
        };

        $scope.applyTemplate = function (model, template) {
            // first ask user if they want to use template values
            vex.dialog.confirm({
                message:
                    'Do you want to replace the currency, payment terms, items, rates, notes, and terms for this ' +
                    escapeHtml($scope.modelTitleSingular.toLowerCase()) +
                    ' with the ' +
                    escapeHtml(template.name) +
                    ' template?',
                callback: function (result) {
                    if (result) {
                        $scope.$apply(function () {
                            $scope.replaceWithTemplate(model, template);
                        });
                    }
                },
            });
        };

        $scope.replaceWithTemplate = function (model, template) {
            if (!template.currency) {
                template.currency = $scope.company.currency;
            }

            if (!model.inherit_terms || !model.payment_terms) {
                model.payment_terms = template.payment_terms;
            }

            let replace = ['currency', 'chase', 'items', 'discounts', 'taxes', 'notes'];

            angular.forEach(replace, function (key) {
                model[key] = angular.copy(template[key]);
            });
        };

        //
        // Private Methods
        //

        function pullQueryParameters() {
            // parse query parameters for filtering and pagination
            applyFilter(angular.copy($location.search()));
        }

        function addToFilter(addedFilter) {
            let filter = angular.copy($scope.filter);
            angular.extend(filter, addedFilter);
            applyFilter(filter);
        }

        function applyFilter(filter) {
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

                if (typeof defaultFilter[i] === 'object') {
                    // JSON decode objects/arrays in the query parameters
                    if (typeof value === 'string' && (value[0] === '{' || value[0] === '[')) {
                        value = angular.fromJson(value);
                    }

                    // Convert simple filter to advanced filter format.
                    // This format is used by query parameters in URLs
                    // and legacy saved filters.
                    if (
                        typeof defaultFilter[i].operator !== 'undefined' &&
                        typeof defaultFilter[i].value !== 'undefined' &&
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

            // memorize the selection in local storage
            memorizeFilter(filter);

            let completeFilter = angular.copy(defaultFilter);
            angular.extend(completeFilter, filter);
            $scope.filter = angular.copy(completeFilter);
            $scope.updateFilterString();

            $scope.findAll();
        }

        function memorizeFilter(filter) {
            filter = angular.copy(filter);
            for (let i in filter) {
                let value = filter[i];
                if (value instanceof Date) {
                    filter[i] = moment(value).unix();
                }
            }

            lss.add($state.current.name + '.applied_filter.' + selectedCompany.id, angular.toJson(filter));
        }

        function recallFilter() {
            let savedFilter = lss.get($state.current.name + '.applied_filter.' + selectedCompany.id);
            if (!savedFilter) {
                return null;
            }

            return angular.fromJson(savedFilter);
        }

        function duplicate(model) {
            let k, i;
            for (i in $scope.deleteKeysForDuplicate) {
                k = $scope.deleteKeysForDuplicate[i];
                delete model[k];
            }

            for (i in $scope.deleteMetadataKeysForDuplicate) {
                k = $scope.deleteMetadataKeysForDuplicate[i];
                delete model.metadata[k];
            }
        }

        function _save(params, isEdit) {
            if (isEdit) {
                let id = params.id;
                delete params.id;
                edit(id, params);
            } else {
                delete params.id;
                create(params);
            }
        }

        function create(params) {
            $scope.saving = true;

            $scope.model.create(
                params,
                function (result) {
                    $scope.saving = false;

                    LeavePageWarning.unblock();

                    if (typeof $scope.postCreate == 'function') {
                        $scope.postCreate(result);
                    }

                    $location.path('/' + modelRoute + '/' + result.id);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function edit(id, params) {
            $scope.saving = true;

            $scope.model.edit(
                {
                    id: id,
                },
                params,
                function (result) {
                    $scope.saving = false;

                    LeavePageWarning.unblock();

                    if (typeof $scope.postSave == 'function') {
                        $scope.postSave(result);
                    }

                    $location.path('/' + modelRoute + '/' + result.id);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function _delete(model) {
            $scope.deleting = true;

            let params = {
                id: model.id,
            };

            if (typeof $scope.preDelete == 'function') {
                $scope.preDelete(params);
            }

            $scope.model.delete(
                params,
                function () {
                    $scope.deleting = false;
                    $scope.postDelete(model);
                },
                function (result) {
                    $scope.deleting = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function _findAll(params) {
            let findAllParams = {
                page: $scope.filter.page,
                per_page: $scope.filter.per_page,
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

            $scope.model.findAll(
                findAllParams,
                function (result, headers) {
                    $scope.total_count = headers('X-Total-Count');
                    $scope.links = Core.parseLinkHeader(headers('Link'));

                    // compute page count from pagination links
                    $scope.page_count = $scope.links.last.match(/[\?\&]page=(\d+)/)[1];

                    // if the requested page exceeds the page count then
                    // go back to the first page
                    if (findAllParams.page > $scope.page_count) {
                        addToFilter({ page: 1 });
                        return;
                    }

                    $scope.postFindAll(result);

                    $scope.loading = false;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
