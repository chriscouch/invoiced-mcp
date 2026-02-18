(function () {
    'use strict';

    angular.module('app.inboxes').controller('NewInboxEmailController', NewInboxEmailController);

    NewInboxEmailController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        '$state',
        'CurrentUser',
        'Inbox',
        'inboxId',
    ];

    function NewInboxEmailController($scope, $modal, $modalInstance, $state, CurrentUser, Inbox, inboxId) {
        $scope.options = {
            message: '',
            cc: [],
            bcc: [],
            to: [],
            subject: '',
            attachments: [],
        };
        $scope.send = function () {
            $scope.saving = true;
            $scope.error = null;
            Inbox.send(
                {
                    id: inboxId,
                },
                $scope.options,
                function (result) {
                    $scope.saving = false;
                    $modalInstance.close(result);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
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
