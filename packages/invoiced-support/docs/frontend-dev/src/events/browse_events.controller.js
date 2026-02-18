(function () {
    'use strict';

    angular.module('app.events').controller('BrowseEventsController', BrowseEventsController);

    BrowseEventsController.$inject = ['$scope', '$modal', 'InvoicedConfig', 'Feature', 'UiFilterService'];

    function BrowseEventsController($scope, $modal, InvoicedConfig, Feature, UiFilterService) {
        $scope.hasFeature = Feature.hasFeature('audit_log');

        //
        // Settings
        //

        $scope.filterStr = '';

        $scope.filter = {};
        let defaultEventFilter = {
            type: '',
            event: '',
            from: '',
        };
        $scope.filter = angular.copy(defaultEventFilter);

        $scope.eventOptions = {
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
            $scope.filter = {};
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
