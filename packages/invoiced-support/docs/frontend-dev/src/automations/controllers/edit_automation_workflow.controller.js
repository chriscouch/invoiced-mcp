(function () {
    'use strict';

    angular.module('app.automations').controller('EditAutomationWorkflowController', EditAutomationWorkflowController);

    EditAutomationWorkflowController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        'LeavePageWarning',
        'Core',
        'AutomationWorkflow',
        'AutomationBuilder',
    ];

    function EditAutomationWorkflowController(
        $scope,
        $state,
        $stateParams,
        LeavePageWarning,
        Core,
        AutomationWorkflow,
        AutomationBuilder,
    ) {
        $scope.loading = 0;

        $scope.save = saveWorkflow;

        LeavePageWarning.watchForm($scope, 'workflowForm');

        if ($stateParams.id) {
            Core.setTitle('Edit Automation Workflow');
            loadWorkflow($stateParams.id);
            $scope.isExisting = true;
        } else {
            Core.setTitle('New Automation Workflow');
            $scope.workflow = {
                name: '',
                description: '',
                object_type: null,
            };
        }

        $scope.objectTypes = [];
        AutomationBuilder.getObjectTypes(function (objectTypes) {
            $scope.objectTypes = objectTypes;
        });

        function loadWorkflow(id) {
            $scope.loading++;

            AutomationWorkflow.find(
                {
                    id: id,
                },
                function (workflow) {
                    $scope.loading--;
                    $scope.workflow = workflow;

                    if ($state.current.name === 'manage.automations.duplicate_workflow') {
                        delete workflow.id;
                        Core.setTitle('New Automation Workflow');
                        $scope.isExisting = false;
                    }
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }

        function saveWorkflow(workflow) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                name: workflow.name,
                description: workflow.description,
            };

            if (workflow.id) {
                AutomationWorkflow.edit(
                    {
                        id: workflow.id,
                    },
                    params,
                    function () {
                        $scope.saving = false;

                        Core.flashMessage('Your automation workflow has been updated.', 'success');

                        LeavePageWarning.unblock();
                        $state.go('manage.settings.automation.list');
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                params.object_type = workflow.object_type;
                AutomationWorkflow.create(
                    params,
                    function (_workflow) {
                        $scope.saving = false;

                        Core.flashMessage('Your automation workflow has been created.', 'success');

                        LeavePageWarning.unblock();
                        $state.go('manage.automations.builder', { id: _workflow.id });
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        }
    }
})();
