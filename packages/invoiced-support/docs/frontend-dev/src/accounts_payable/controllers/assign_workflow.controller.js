(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('AssignWorkflowController', AssignWorkflowController);

    AssignWorkflowController.$inject = ['$scope', '$modalInstance'];

    function AssignWorkflowController($scope, $modalInstance) {
        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };
        $scope.assign = function () {
            $modalInstance.close($scope.workflow);
        };
    }
})();
