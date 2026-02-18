(function () {
    'use strict';

    angular.module('app.core').controller('ColumnArrangementController', ColumnArrangementController);

    ColumnArrangementController.$inject = ['$scope', '$modalInstance', 'ColumnArrangementService', 'type', 'columns'];

    function ColumnArrangementController($scope, $modalInstance, ColumnArrangementService, type, columns) {
        $scope.sortableOptionsList = createOptions();

        $scope.fields = columns;
        $scope.selected = ColumnArrangementService.getSelectedColumns(type, $scope.fields);
        let selectedIds = $scope.selected.map(function (filed) {
            return filed.id;
        });
        $scope.fields = $scope.fields
            .filter(function (field) {
                return selectedIds.indexOf(field.id) === -1;
            })
            .sort(function (a, b) {
                return a.name.localeCompare(b.name);
            });

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.save = function () {
            let saved = ColumnArrangementService.save(type, $scope.selected);
            if (saved) {
                $modalInstance.close($scope.selected);
            }
        };
    }

    function createOptions() {
        return {
            placeholder: 'field',
            connectWith: '.fields-container',
            start: function (e, ui) {
                ui.item.addClass('active');
            },
            stop: function (e, ui) {
                ui.item.removeClass('active');
            },
        };
    }
})();
