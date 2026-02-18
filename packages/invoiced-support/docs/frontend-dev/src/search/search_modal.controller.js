(function () {
    'use strict';

    angular.module('app.content').controller('SearchModalController', SearchModalController);

    SearchModalController.$inject = ['$scope', '$modalInstance', '$state'];

    function SearchModalController($scope, $modalInstance, $state) {
        $scope.search = function (query) {
            if (!query) {
                return;
            }

            $state.go('manage.search', {
                q: query,
            });
            $scope.close();
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });
    }
})();
