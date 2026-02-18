(function () {
    'use strict';

    angular.module('app.inboxes').directive('threadModal', threadModal);

    function threadModal() {
        return {
            restrict: 'E',
            template:
                '<a href="" ng-click="threadModal()" ng-if="(\'emails.send\'|hasPermission) && (\'email_sending\'|hasFeature)">' +
                '<span class="fas fa-plus"></span>' +
                '</a>',
            scope: {
                inboxId: '=',
            },
            controller: [
                '$state',
                '$scope',
                '$modal',
                'LeavePageWarning',
                function ($state, $scope, $modal, LeavePageWarning) {
                    $scope.threadModal = function () {
                        LeavePageWarning.block();
                        const modalInstance = $modal.open({
                            templateUrl: 'inboxes/views/new-email.html',
                            controller: 'NewInboxEmailController',
                            backdrop: 'static',
                            size: 'lg',
                            keyboard: false,
                            resolve: {
                                inboxId: function () {
                                    return $scope.inboxId;
                                },
                            },
                        });

                        modalInstance.result.then(
                            function (result) {
                                LeavePageWarning.unblock();
                                $state.go('manage.inboxes.browse.view_thread', {
                                    id: $scope.inboxId,
                                    threadId: result.thread_id,
                                    status: '',
                                });
                            },
                            function () {
                                // canceled
                                LeavePageWarning.unblock();
                            },
                        );
                    };
                },
            ],
        };
    }
})();
