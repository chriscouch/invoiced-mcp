(function () {
    'use strict';

    angular.module('app.accounts_payable').directive('assignWorkflow', assignWorkflow);

    function assignWorkflow() {
        return {
            restrict: 'E',
            template:
                '<a href="" ng-click="assign()">' +
                '<span ng-if="workflow">Unassign Workflow</span>' +
                '<span ng-if="!workflow">Assign Workflow</span>' +
                '</a>',
            scope: {
                workflow: '=',
                callback: '=',
            },
            replace: true,
            controller: [
                '$scope',
                '$modal',
                'LeavePageWarning',
                function ($scope, $modal, LeavePageWarning) {
                    $scope.assign = assign;
                    function assign() {
                        if ($scope.workflow) {
                            $scope.callback(null);
                        } else {
                            LeavePageWarning.block();

                            const modalInstance = $modal.open({
                                templateUrl: 'accounts_payable/views/bills/assign-workflow.html',
                                controller: 'AssignWorkflowController',
                                backdrop: 'static',
                                keyboard: false,
                                size: 'md',
                            });
                            modalInstance.result.then(
                                function (workflow) {
                                    $scope.callback(workflow);
                                    LeavePageWarning.unblock();

                                    // Core.flashMessage('Approval workflow has been assigned', 'success');
                                },
                                function () {
                                    // canceled
                                    LeavePageWarning.unblock();
                                },
                            );
                        }
                    }
                },
            ],
        };
    }
})();
