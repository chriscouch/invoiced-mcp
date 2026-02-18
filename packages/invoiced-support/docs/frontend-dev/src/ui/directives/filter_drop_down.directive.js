/* globals vex */
(function () {
    'use strict';

    angular.module('app.components').directive('filterDropdown', filterDropdown);

    function filterDropdown() {
        return {
            restrict: 'E',
            templateUrl: 'ui/views/filter-dropdown.html',
            scope: {
                type: '@',
                savedFilters: '=',
                applyFilter: '=',
                filter: '=',
            },
            controller: [
                '$scope',
                '$translate',
                '$modal',
                'Core',
                'Member',
                'LeavePageWarning',
                'Ui',
                'UiFilterService',
                function ($scope, $translate, $modal, Core, Member, LeavePageWarning, Ui, UiFilterService) {
                    $scope.member = null;
                    $scope.savedFilters = [];
                    loadFilters($scope.type);

                    $scope.createNamedFilter = function (type) {
                        UiFilterService.saveFilter(
                            {
                                type: type,
                                settings: $scope.filter,
                                private: true,
                            },
                            function (result) {
                                Core.flashMessage($translate.instant('ui.filters.messages.created'), 'success');
                                $scope.savedFilters.push(result);
                                $scope.savedFilters = UiFilterService.sortFilters($scope.savedFilters);
                            },
                        );
                    };

                    $scope.editNamedFilter = function (filter) {
                        UiFilterService.saveFilter(filter, function (result) {
                            Core.flashMessage($translate.instant('ui.filters.messages.updated'), 'success');
                            angular.extend(filter, result);
                            $scope.filters = UiFilterService.sortFilters($scope.filters);
                        });
                    };

                    $scope.deleteNamedFilter = function (filter) {
                        vex.dialog.confirm({
                            message: $translate.instant('ui.filters.messages.delete'),
                            callback: function (result) {
                                if (!result) {
                                    return;
                                }
                                Ui.delete(
                                    {
                                        id: filter.id,
                                    },
                                    function () {
                                        Core.flashMessage($translate.instant('ui.filters.messages.deleted'), 'success');
                                        $scope.savedFilters = $scope.savedFilters.filter(function (result) {
                                            return filter.id !== result.id;
                                        });
                                    },
                                    function (error) {
                                        Core.flashMessage(error, 'error');
                                    },
                                );
                            },
                        });
                    };

                    $scope.applyNamedFilter = function (filter) {
                        $scope.applyFilter(filter.settings);
                    };

                    function loadFilters(type) {
                        Member.current(
                            function (member) {
                                if (member) {
                                    $scope.member = member;
                                }
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                        try {
                            Ui.list(
                                {
                                    type: type,
                                },
                                function (filters) {
                                    $scope.savedFilters = UiFilterService.sortFilters(filters).map(function (filter) {
                                        if (filter.settings.start_date) {
                                            filter.settings.start_date = new Date(filter.settings.start_date);
                                        }
                                        if (filter.settings.end_date) {
                                            filter.settings.end_date = new Date(filter.settings.end_date);
                                        }
                                        return filter;
                                    });
                                },
                                function (result) {
                                    Core.showMessage(result.data.message, 'error');
                                },
                            );
                        } catch (e) {}
                    }
                },
            ],
        };
    }
})();
