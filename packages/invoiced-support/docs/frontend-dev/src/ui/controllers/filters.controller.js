(function () {
    'use strict';

    angular.module('app.core').controller('SaveUiFilterController', SaveUiFilterController);

    SaveUiFilterController.$inject = ['$scope', '$modalInstance', 'Core', 'Ui', 'filter'];

    function SaveUiFilterController($scope, $modalInstance, Core, Ui, filter) {
        $scope.filter = angular.copy(filter);

        $scope.save = function (filter) {
            $scope.saving = true;
            $scope.error = null;

            if (filter.id) {
                update(filter);
            } else {
                create(filter);
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function create(filter) {
            Ui.create(
                filter,
                function (_filter) {
                    $scope.saving = false;
                    $modalInstance.close(_filter);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function update(filter) {
            Ui.update(
                {
                    id: filter.id,
                },
                {
                    name: filter.name,
                    private: filter.private,
                },
                function (_filter) {
                    $scope.saving = false;
                    $modalInstance.close(_filter);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
