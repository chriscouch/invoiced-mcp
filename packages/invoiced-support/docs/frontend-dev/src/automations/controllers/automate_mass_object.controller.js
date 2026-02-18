(function () {
    'use strict';

    angular.module('app.automations').controller('AutomateMassObjectController', AutomateMassObjectController);

    AutomateMassObjectController.$inject = [
        '$scope',
        '$modalInstance',
        'Core',
        'AutomationWorkflow',
        'objectType',
        'options',
        'count',
    ];

    function AutomateMassObjectController(
        $scope,
        $modalInstance,
        Core,
        AutomationWorkflow,
        objectType,
        options,
        count,
    ) {
        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.automations = [];
        $scope.objectType = objectType;
        $scope.count = count;
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
                            description: workflow.description,
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

        function runAutomation(automation) {
            $scope.starting = true;
            AutomationWorkflow.massTrigger(
                {
                    id: automation.id,
                },
                {
                    options: options,
                },
                function () {
                    $scope.starting = false;
                    Core.flashMessage(
                        'The ' + automation.name + ' automation has been initiated for the selected objects.',
                        'success',
                    );
                    $modalInstance.close();
                },
                function (result) {
                    $scope.starting = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function enroll(automation) {
            $scope.starting = true;
            AutomationWorkflow.massEnroll(
                {
                    id: automation.id,
                },
                {
                    options: options,
                },
                function () {
                    $scope.starting = false;
                    Core.flashMessage(
                        'The selected objects will be enrolled in the "' + automation.name + '" automation.',
                        'success',
                    );
                    $modalInstance.close();
                },
                function (result) {
                    $scope.starting = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function unEnroll(automation) {
            $scope.starting = true;
            AutomationWorkflow.massUnEnroll(
                {
                    id: automation.id,
                },
                {
                    options: options,
                },
                function () {
                    $scope.starting = false;
                    Core.flashMessage(
                        'The selected objects will be unenrolled from the "' + automation.name + '" automation.',
                        'success',
                    );
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
