(function () {
    'use strict';

    angular.module('app.reports').controller('ReportFieldSelectorController', ReportFieldSelectorController);

    ReportFieldSelectorController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        'ReportBuilder',
        'selectedCompany',
        'excludedObjects',
        'object',
        'options',
    ];

    function ReportFieldSelectorController(
        $scope,
        $modalInstance,
        $modal,
        ReportBuilder,
        selectedCompany,
        excludedObjects,
        object,
        options,
    ) {
        $scope.availableFields = [];
        $scope.selectedFields = [];
        $scope.object = object;
        $scope.multiple = options.multiple || false;

        $scope.selectField = selectField;
        $scope.deselectField = deselectField;
        $scope.add = add;
        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        load(object);

        function load(_object) {
            $scope.loading = true;
            ReportBuilder.initialize(function (reportObjects, availableFields) {
                angular.forEach(availableFields[_object], function (field) {
                    // Excludes joined objects that reflect back to a parent object.
                    // We want to prevent something like: Customer -> Card -> Customer -> Card (repeat)
                    if (field.type === 'join' && excludedObjects.indexOf(field.join_object) !== -1) {
                        return;
                    }

                    field = angular.copy(field);
                    $scope.availableFields.push(field);
                });
                sort();
                $scope.loading = false;
            });
        }

        function selectField(field) {
            if (field.type === 'join') {
                selectJoinFields(field);
                return;
            }

            for (let i in $scope.availableFields) {
                let field2 = $scope.availableFields[i];
                if (field2.id === field.id && field2.group === field.group) {
                    $scope.availableFields.splice(i, 1);
                    break;
                }
            }

            // Selecting a single field
            if (!$scope.multiple) {
                $scope.add([field]);
            } else {
                // Selecting multiple fields
                $scope.selectedFields.push(field);
                sort();
            }
        }

        function selectJoinFields(field) {
            const modalInstance = $modal.open({
                templateUrl: 'reports/views/field-selector.html',
                controller: 'ReportFieldSelectorController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    options: function () {
                        return options;
                    },
                    excludedObjects: function () {
                        return excludedObjects.concat(object);
                    },
                    object: function () {
                        return field.join_object;
                    },
                },
            });

            modalInstance.result.then(
                function (fields) {
                    // Prepend join field name and ID to the added fields
                    angular.forEach(fields, function (field2) {
                        // Prepend object name to field name to remove ambiguity
                        if (field2.name.indexOf(field.name) === -1) {
                            field2.name = field.name + ' ' + field2.name;
                        }
                        field2.id = field.id + '.' + field2.id;
                    });

                    // Selecting a single field
                    if (!$scope.multiple) {
                        $scope.add(fields);
                    } else {
                        // Selecting multiple fields
                        $scope.selectedFields = $scope.selectedFields.concat(fields);
                    }
                },
                function () {
                    // canceled
                },
            );
        }

        function deselectField(field) {
            for (let i in $scope.selectedFields) {
                let field2 = $scope.selectedFields[i];
                if (field2.id === field.id && field2.group === field.group) {
                    $scope.selectedFields.splice(i, 1);
                    break;
                }
            }
            $scope.availableFields.push(field);
            sort();
        }

        function add(selected) {
            $modalInstance.close(selected);
        }

        function sort() {
            $scope.availableFields.sort(sortFn);
            $scope.selectedFields.sort(sortFn);
        }

        function sortFn(a, b) {
            return a.name.localeCompare(b.name);
        }
    }
})();
