(function () {
    'use strict';

    angular.module('app.components').controller('ListFilterController', ListFilterController);

    ListFilterController.$inject = [
        '$scope',
        '$modalInstance',
        'UiFilterService',
        'selectedCompany',
        'filter',
        'filterFields',
    ];

    function ListFilterController($scope, $modalInstance, UiFilterService, selectedCompany, filter, filterFields) {
        $scope.filter = angular.copy(filter);
        $scope.company = selectedCompany;
        $scope.dateOptions = {
            dateFormat: 'Y-m-d',
            altFormat: 'Y-m-d',
            altInput: true,
            allowInput: true,
        };
        $scope.datetimeOptions = {
            enableTime: true,
            dateFormat: 'Z',
            altInput: true,
            altFormat: 'Y-m-d h:i K',
            allowInput: true,
        };
        $scope.selectFieldMode = false;
        $scope.fieldsForSelect = [];

        $scope.canRemoveField = canRemoveField;
        $scope.removeField = removeField;
        $scope.goToSelectField = goToSelectField;
        $scope.cancelSelectMode = cancelSelectMode;
        $scope.addField = addField;

        $scope.applyFilter = function (filter) {
            $modalInstance.close(filter);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        // Sort fields alphabetically by label
        filterFields.sort(function (a, b) {
            return a.label.localeCompare(b.label);
        });

        buildForm(filter, filterFields);

        function buildForm(filter, filterFields) {
            $scope.filterFields = [];

            // Only show filter fields which have a value
            angular.forEach(filterFields, function (field) {
                if (UiFilterService.hasValue($scope.filter, field)) {
                    $scope.filterFields.push(field);
                }
            });

            // Build the available fields to be added
            $scope.fieldsForSelect = getAvailableFields(filterFields, $scope.filterFields);
        }

        function getAvailableFields(allFields, selectedFields) {
            // Collect all fields which are not currently displayed
            let alreadyAdded = {};
            angular.forEach(selectedFields, function (field) {
                alreadyAdded[field.id] = true;
            });

            let result = [];
            angular.forEach(allFields, function (field) {
                if (!alreadyAdded[field.id]) {
                    result.push(field);
                }
            });

            return result;
        }

        function canRemoveField(field) {
            return field.type !== 'sort';
        }

        function removeField(field, $index) {
            $scope.filterForm.$setDirty();

            // Clear the value on the filter
            UiFilterService.clearFieldValue($scope.filter, field);

            // Remove it from the form
            $scope.filterFields.splice($index, 1);
            // Rebuild the available fields to be added
            $scope.fieldsForSelect = getAvailableFields(filterFields, $scope.filterFields);
        }

        function goToSelectField() {
            $scope.selectFieldMode = true;
        }

        function cancelSelectMode() {
            $scope.selectFieldMode = false;
        }

        function addField(field) {
            $scope.filterForm.$setDirty();
            $scope.filterFields.push(field);
            $scope.selectFieldMode = false;

            // Rebuild the available fields to be added
            $scope.fieldsForSelect = getAvailableFields(filterFields, $scope.filterFields);

            // Sets the default field value on the filter
            UiFilterService.setDefaultFieldValue($scope.filter, field);
        }
    }
})();
