/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('AutomationSettingsController', AutomationSettingsController);

    AutomationSettingsController.$inject = [
        '$scope',
        '$modal',
        'Company',
        'selectedCompany',
        'Core',
        'ObjectDeepLink',
        'AutomationWorkflow',
    ];

    function AutomationSettingsController(
        $scope,
        $modal,
        Company,
        selectedCompany,
        Core,
        ObjectDeepLink,
        AutomationWorkflow,
    ) {
        $scope.workflows = [];
        $scope.deleting = {};

        $scope.setEnabled = function (workflow, enabled) {
            $scope.deleting[workflow.id] = true;
            $scope.error = null;

            AutomationWorkflow.edit(
                {
                    id: workflow.id,
                },
                {
                    enabled: enabled,
                },
                function (_workflow) {
                    angular.extend(workflow, _workflow);
                    delete $scope.deleting[workflow.id];

                    Core.flashMessage('The workflow, ' + workflow.name + ', has been updated', 'success');
                },
                function (result) {
                    delete $scope.deleting[workflow.id];
                    $scope.error = result.data;
                },
            );
        };

        $scope.delete = function (workflow) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this workflow?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[workflow.id] = true;
                        $scope.error = null;

                        AutomationWorkflow.delete(
                            {
                                id: workflow.id,
                            },
                            function () {
                                delete $scope.deleting[workflow.id];

                                Core.flashMessage('The workflow, ' + workflow.name + ', has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.workflows) {
                                    if ($scope.workflows[i].id == workflow.id) {
                                        $scope.workflows.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                delete $scope.deleting[workflow.id];
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('Automations');
        load();

        function load() {
            $scope.loading = true;

            AutomationWorkflow.findAll(
                function (workflows) {
                    $scope.loading = false;
                    $scope.workflows = workflows.map(function (workflow) {
                        if (workflow.object_type === 'payment_plan') {
                            workflow.enrollments = 'payment_plans?automation=' + workflow.id;
                            return workflow;
                        }
                        if (
                            workflow.enabled &&
                            [
                                'customer',
                                'invoice',
                                'estimate',
                                'credit_note',
                                'subscription',
                                'task',
                                'payment_plans',
                                'promise_to_pay',
                                'vendor',
                            ].indexOf(workflow.object_type) !== -1
                        ) {
                            workflow.enrollments =
                                ObjectDeepLink.getUrl(workflow.object_type) + '?automation=' + workflow.id;
                        }
                        return workflow;
                    });
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
