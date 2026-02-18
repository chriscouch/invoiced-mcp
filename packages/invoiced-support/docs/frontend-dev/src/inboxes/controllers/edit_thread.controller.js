(function () {
    'use strict';

    angular.module('app.inboxes').controller('EditThreadController', EditThreadController);

    EditThreadController.$inject = ['$scope', '$modalInstance', 'Core', 'EmailThread', 'thread'];

    function EditThreadController($scope, $modalInstance, Core, EmailThread, thread) {
        $scope.thread = thread;

        $scope.clear = function () {
            $modalInstance.close(null);
        };

        $scope.save = function () {
            EmailThread.edit(
                {
                    id: $scope.thread.id,
                },
                {
                    name: $scope.thread.name,
                    customer_id: $scope.thread.customer_id ? $scope.thread.customer_id.id : null,
                },
                function () {
                    $modalInstance.close($scope.thread);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
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
