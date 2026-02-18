(function () {
    'use strict';

    angular.module('app.automations').controller('AutomationBuilderController', AutomationBuilderController);

    AutomationBuilderController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        '$modal',
        'LeavePageWarning',
        'Core',
        'AutomationWorkflow',
    ];

    function AutomationBuilderController(
        $scope,
        $state,
        $stateParams,
        $modal,
        LeavePageWarning,
        Core,
        AutomationWorkflow,
    ) {
        $scope.loading = 0;
        $scope.triggers = [];
        $scope.steps = [];
        $scope.workflowVersion = 1;
        $scope.isDraft = true;
        let versionId = null;
        let hasChanged = false;

        $scope.addTrigger = addTrigger;
        $scope.editTrigger = editTrigger;
        $scope.deleteTrigger = deleteTrigger;
        $scope.addStep = addStep;
        $scope.editStep = editStep;
        $scope.deleteStep = deleteStep;
        $scope.save = saveWorkflow;

        Core.setTitle('Automation Builder');

        loadWorkflow($stateParams.id);

        function loadWorkflow(id) {
            $scope.loading++;

            AutomationWorkflow.find(
                {
                    id: id,
                    expand: 'current_version,draft_version',
                },
                function (workflow) {
                    $scope.loading--;
                    $scope.workflow = workflow;

                    let version = null;
                    if (workflow.draft_version) {
                        version = workflow.draft_version;
                        $scope.isDraft = true;
                    } else if (workflow.current_version) {
                        version = workflow.current_version;
                        $scope.isDraft = false;
                    } else {
                        $scope.isDraft = true;
                    }

                    if (version) {
                        versionId = version.id;
                        $scope.triggers = version.triggers;
                        $scope.steps = version.steps;
                        $scope.workflowVersion = version.version;
                    }
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }

        function saveWorkflow(workflow, triggers, steps, publish) {
            $scope.saving = true;
            $scope.error = null;

            // If this is the current published version we are editing
            // then we MUST create a new version to make any changes.
            // Also, if the current published version is being saved as
            // draft then we MUST create a new version.
            if (
                versionId &&
                workflow.current_version &&
                versionId === workflow.current_version.id &&
                (hasChanged || !publish)
            ) {
                versionId = null;
            }

            // Do not save the workflow if it has not changed.
            if (versionId && !hasChanged) {
                if (publish) {
                    publishVersion(workflow, versionId);
                } else {
                    publishDraft(workflow, versionId);
                }

                return;
            }

            let params = {
                triggers: [],
                steps: [],
            };

            angular.forEach(triggers, function (trigger) {
                let trigger2 = {
                    trigger_type: trigger.trigger_type,
                };
                if (trigger.id) {
                    trigger2.id = trigger.id;
                }
                if (trigger.event_type) {
                    trigger2.event_type = trigger.event_type;
                }
                if (trigger.r_rule) {
                    trigger2.r_rule = trigger.r_rule;
                }
                params.triggers.push(trigger2);
            });

            angular.forEach(steps, function (step) {
                let step2 = {
                    action_type: step.action_type,
                    settings: step.settings,
                };
                if (step.id) {
                    step2.id = step.id;
                }
                params.steps.push(step2);
            });

            if (versionId) {
                AutomationWorkflow.editVersion(
                    {
                        workflow_id: workflow.id,
                        id: versionId,
                    },
                    params,
                    function (version) {
                        if (publish) {
                            publishVersion(workflow, version.id);
                        } else {
                            publishDraft(workflow, version.id);
                        }
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                AutomationWorkflow.createVersion(
                    {
                        workflow_id: workflow.id,
                    },
                    params,
                    function (version) {
                        if (publish) {
                            publishVersion(workflow, version.id);
                        } else {
                            publishDraft(workflow, version.id);
                        }
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        }

        function publishVersion(workflow, versionId) {
            // clear draft if it is set to current version
            let draftVersion = workflow.draft_version ? workflow.draft_version.id : null;
            if (draftVersion === versionId) {
                draftVersion = null;
            }

            AutomationWorkflow.edit(
                {
                    id: workflow.id,
                },
                {
                    enabled: true,
                    current_version: versionId,
                    draft_version: draftVersion,
                },
                finishSave,
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function publishDraft(workflow, versionId) {
            AutomationWorkflow.edit(
                {
                    id: workflow.id,
                },
                {
                    draft_version: versionId,
                },
                finishSave,
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function finishSave() {
            $scope.saving = false;
            Core.flashMessage('Your automation workflow has been updated.', 'success');
            LeavePageWarning.unblock();
            $state.go('manage.settings.automation.list');
        }

        function addTrigger(workflow) {
            if (LeavePageWarning.canLeave()) {
                LeavePageWarning.block();
            }

            const modalInstance = $modal.open({
                templateUrl: 'automations/views/add-trigger.html',
                controller: 'AddAutomationTriggerController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    workflow: () => workflow,
                    trigger: () => null,
                },
            });

            modalInstance.result.then(function (trigger) {
                $scope.triggers.push(trigger);
                hasChanged = true;
            });
        }

        function editTrigger(workflow, trigger) {
            if (LeavePageWarning.canLeave()) {
                LeavePageWarning.block();
            }

            const modalInstance = $modal.open({
                templateUrl: 'automations/views/add-trigger.html',
                controller: 'AddAutomationTriggerController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    workflow: () => workflow,
                    trigger: () => trigger,
                },
            });

            modalInstance.result.then(function (trigger2) {
                angular.extend(trigger, trigger2);
                hasChanged = true;
            });
        }

        function deleteTrigger($index) {
            $scope.triggers.splice($index, 1);
            hasChanged = true;
        }

        function addStep(workflow, $index) {
            if (LeavePageWarning.canLeave()) {
                LeavePageWarning.block();
            }
            const modalInstance = $modal.open({
                templateUrl: 'automations/views/add-step.html',
                controller: 'AddAutomationStepController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    workflow: function () {
                        return workflow;
                    },
                    step: function () {
                        return null;
                    },
                },
            });

            modalInstance.result.then(function (step) {
                $scope.steps.splice($index + 1, 0, step);
                hasChanged = true;
            });
        }

        function editStep(workflow, step) {
            if (LeavePageWarning.canLeave()) {
                LeavePageWarning.block();
            }
            const modalInstance = $modal.open({
                templateUrl: 'automations/views/add-step.html',
                controller: 'AddAutomationStepController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    workflow: function () {
                        return workflow;
                    },
                    step: function () {
                        return step;
                    },
                },
            });

            modalInstance.result.then(function (step2) {
                angular.extend(step, step2);
                hasChanged = true;
            });
        }

        function deleteStep($index) {
            $scope.steps.splice($index, 1);
            hasChanged = true;
        }
    }
})();
