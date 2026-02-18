(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('AssignApproverController', AssignApproverController);

    AssignApproverController.$inject = ['$scope', '$modalInstance', 'Core', 'Task', 'doc'];

    function AssignApproverController($scope, $modalInstance, Core, Task, doc) {
        $scope.user = null;

        $scope.assign = function () {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                name: 'Approve document ' + doc.number,
                action: 'approve_' + doc.object,
                user_id: $scope.user.id,
                due_date: Math.floor(new Date().getTime() / 1000),
            };
            params[doc.object + '_id'] = doc.id;

            Task.new(
                params,
                function () {
                    $scope.saving = false;
                    $modalInstance.close();
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };
    }
})();
