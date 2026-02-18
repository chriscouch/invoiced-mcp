(function () {
    'use strict';

    angular.module('app.events').controller('ObjectHistoryController', ObjectHistoryController);

    ObjectHistoryController.$inject = ['$scope', '$modal', 'InvoicedConfig', 'UiFilterService'];

    function ObjectHistoryController($scope, $modal, InvoicedConfig, UiFilterService) {
        let defaultEventFilter = UiFilterService.getDefaultFilter($scope.filterFields(), 25);
        $scope.filter = angular.copy(defaultEventFilter);

        $scope.eventOptions = {
            relatedTo: $scope.modelObjectType + ',' + $scope.modelId,
            byDay: true,
            perPage: 25,
            filterFields: filterFields(),
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
                    filterFields: function () {
                        return $scope.eventOptions.filterFields;
                    },
                },
            });

            modalInstance.result.then($scope.applyEventFilter, function () {
                // canceled
            });
        };

        $scope.applyEventFilter = function (filter) {
            $scope.filter = angular.copy(filter);
            $scope.filterStr = UiFilterService.buildFilterString(
                filter,
                $scope.eventOptions.filterFields,
                function (enrichedStr) {
                    $scope.filterStr = enrichedStr;
                },
            );
        };

        $scope.clearEventFilter = function () {
            $scope.filter = angular.copy(defaultEventFilter);
            $scope.filterStr = '';
        };

        function filterFields() {
            let eventTypes = [];
            angular.forEach(InvoicedConfig.eventTypes, function (eventType) {
                eventTypes.push({ text: eventType, value: eventType });
            });

            return [
                {
                    id: 'object_type',
                    label: 'Object Type',
                    serialize: false,
                    type: 'enum',
                    values: InvoicedConfig.eventObjectTypeFilter,
                },
                {
                    id: 'type',
                    label: 'Event Type',
                    type: 'enum',
                    values: eventTypes,
                },
                {
                    id: 'from',
                    label: 'From',
                    serialize: false,
                    type: 'enum',
                    values: [
                        {
                            value: 'me',
                            text: 'Me',
                        },
                        {
                            value: 'team',
                            text: 'My Team',
                        },
                        {
                            value: 'customer',
                            text: 'Customer',
                        },
                        {
                            value: 'invoiced',
                            text: 'Invoiced',
                        },
                        {
                            value: 'api',
                            text: 'API',
                        },
                    ],
                },
                {
                    id: 'timestamp',
                    label: 'Timestamp',
                    type: 'datetime',
                },
            ];
        }
    }
})();
