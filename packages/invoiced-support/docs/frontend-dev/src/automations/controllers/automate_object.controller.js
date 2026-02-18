(function () {
    'use strict';

    angular.module('app.automations').controller('AutomateObjectController', AutomateObjectController);

    AutomateObjectController.$inject = [
        '$scope',
        '$modalInstance',
        'Core',
        'AutomationWorkflow',
        'objectType',
        'objectId',
    ];

    function AutomateObjectController($scope, $modalInstance, Core, AutomationWorkflow, objectType, objectId) {
        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.automations = [];
        $scope.objectType = objectType;
        $scope.selectedAutomation = null;

        $scope.select = select;
        $scope.runAutomation = runAutomation;
        $scope.enroll = enroll;
        $scope.unEnroll = unEnroll;

        load();

        function load() {
            $scope.loading = true;
            AutomationWorkflow.findAll(
                {
                    'filter[enabled]': 1,
                    'filter[object_type]': objectType,
                    expand: 'current_version',
                    object_id: objectId,
                    include: 'enrollment',
                    sort: 'name ASC',
                },
                function (workflows) {
                    $scope.loading = false;
                    $scope.automations = [];
                    angular.forEach(workflows, function (workflow) {
                        if (!workflow.current_version) {
                            return;
                        }

                        const workflow2 = {
                            id: workflow.id,
                            name: workflow.name,
                            enrollment: workflow.enrollment,
                            Manual: false,
                            Schedule: false,
                        };

                        let shouldAdd = false;
                        for (let i in workflow.current_version.triggers) {
                            let trigger = workflow.current_version.triggers[i];
                            if (trigger.trigger_type === 'Manual' || trigger.trigger_type === 'Schedule') {
                                workflow2[trigger.trigger_type] = true;
                                shouldAdd = true;
                            }
                        }

                        if (shouldAdd) {
                            $scope.automations.push(workflow2);
                        }
                    });
                },
                function () {
                    $scope.loading = false;
                },
            );
        }

        function runAutomation(automation, $index) {
            if ($scope.starting) {
                return;
            }

            $scope.starting = true;
            AutomationWorkflow.manualTrigger(
                {
                    workflow: automation.id,
                    object_type: objectType,
                    object_id: objectId,
                },
                function () {
                    $scope.starting = false;
                    $scope.automations.splice($index, 1);
                    Core.flashMessage('Your automation has been queued', 'success');
                    $modalInstance.close();
                },
                function (result) {
                    $scope.starting = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function enroll(automation, $index) {
            if ($scope.starting) {
                return;
            }

            AutomationWorkflow.enroll(
                {
                    workflow: automation.id,
                    object_id: objectId,
                },
                function (data) {
                    $scope.starting = false;
                    $scope.automations[$index].enrollment = data.id;
                    Core.flashMessage('The object has been enrolled in the automation.', 'success');
                    $modalInstance.close();
                },
                function (result) {
                    $scope.starting = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function unEnroll(automation, $index) {
            if ($scope.starting) {
                return;
            }

            $scope.starting = true;
            AutomationWorkflow.unEnroll(
                {
                    id: automation.enrollment,
                },
                function () {
                    $scope.starting = false;
                    $scope.automations[$index].enrollment = null;
                    Core.flashMessage('The object has been unenrolled from the automation.', 'success');
                    $modalInstance.close();
                },
                function (result) {
                    $scope.starting = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function select(automation) {
            $scope.selectedAutomation = automation.id;
        }
    }
})();
