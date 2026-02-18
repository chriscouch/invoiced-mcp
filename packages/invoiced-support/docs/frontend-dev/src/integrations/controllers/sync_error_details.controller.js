(function () {
    'use strict';

    angular.module('app.integrations').controller('SyncErrorDetailsController', SyncErrorDetailsController);

    SyncErrorDetailsController.$inject = [
        '$scope',
        '$modalInstance',
        'Core',
        'ReconciliationError',
        'reconciliationError',
    ];

    function SyncErrorDetailsController($scope, $modalInstance, Core, ReconciliationError, reconciliationError) {
        $scope.reconciliationError = reconciliationError;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.retry = retry;
        $scope.ignore = ignore;

        function retry() {
            if ($scope.saving) {
                return;
            }

            $scope.saving = true;
            ReconciliationError.retry(
                {
                    id: reconciliationError.id,
                },
                function (updated) {
                    $scope.saving = false;
                    Core.flashMessage('This record has been queued for retry.', 'success');
                    angular.extend(reconciliationError, updated);
                    $modalInstance.close(reconciliationError);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function ignore() {
            if ($scope.saving) {
                return;
            }

            $scope.saving = false;
            ReconciliationError.delete(
                {
                    id: reconciliationError.id,
                },
                function () {
                    $scope.saving = false;
                    Core.flashMessage('This reconciliation error has been ignored.', 'success');
                    $modalInstance.close(null);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
