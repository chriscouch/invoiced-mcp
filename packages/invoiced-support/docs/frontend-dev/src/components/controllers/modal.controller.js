(function () {
    'use strict';

    angular.module('app.components').controller('ModalController', ModalController);

    ModalController.$inject = ['$scope', '$modalInstance'];

    function ModalController($scope, $modalInstance) {
        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });
    }
})();
