/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('ApprovalWorkflowSettingsController', ApprovalWorkflowSettingsController);

    ApprovalWorkflowSettingsController.$inject = [
        '$scope',
        'Company',
        'selectedCompany',
        'Core',
        'AutoNumberSequence',
        'ApprovalWorkflow',
    ];

    function ApprovalWorkflowSettingsController(
        $scope,
        Company,
        selectedCompany,
        Core,
        AutoNumberSequence,
        ApprovalWorkflow,
    ) {
        $scope.company = angular.copy(selectedCompany);
        Core.setTitle('Approval Workflow Settings');

        $scope.workflows = [];
        $scope.deleting = [];
        $scope.pageCount = 1;
        $scope.filter = { per_page: 100, page: 1 };

        let showError = function (result) {
            $scope.error = result.data;
        };
        let unsetProperty = function (workflow, property) {
            angular.forEach($scope.workflows, function (result) {
                if (workflow.id === result.id) {
                    result[property] = 0;
                    return false;
                }
                return true;
            });
        };

        $scope.setDefault = function (workflow) {
            ApprovalWorkflow.setDefault(
                {
                    id: workflow.id,
                },
                {},
                function () {
                    angular.forEach($scope.workflows, function (result) {
                        result.default = 0;
                        if (workflow.id === result.id) {
                            result.default = 1;
                        }

                        return true;
                    });
                },
                showError,
            );
        };

        $scope.unSetDefault = function (workflow) {
            ApprovalWorkflow.unSetDefault(
                {
                    id: workflow.id,
                },
                {},
                function () {
                    unsetProperty(workflow, 'default');
                },
                showError,
            );
        };

        $scope.enable = function (workflow) {
            ApprovalWorkflow.enable(
                {
                    id: workflow.id,
                },
                {},
                function () {
                    angular.forEach($scope.workflows, function (result) {
                        if (workflow.id === result.id) {
                            result.enabled = 1;
                        }

                        return true;
                    });
                },
                showError,
            );
        };

        $scope.disable = function (workflow) {
            ApprovalWorkflow.disable(
                {
                    id: workflow.id,
                },
                {},
                function () {
                    unsetProperty(workflow, 'enabled');
                },
                showError,
            );
        };

        $scope.delete = function (workflow, $index) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this approval workflow?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[workflow.id] = true;
                        $scope.error = null;

                        ApprovalWorkflow.delete(
                            {
                                id: workflow.id,
                            },
                            {},
                            function () {
                                $scope.deleting[workflow.id] = false;
                                $scope.workflows.splice($index, 1);
                            },
                            function (result) {
                                $scope.deleting[workflow.id] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        $scope.goToPage = function (page) {
            $scope.loading = 1;
            $scope.filter.page = page;

            page = parseInt(page) || 1;

            if (page < 1 || page > $scope.pageCount) {
                return;
            }

            ApprovalWorkflow.findAll(
                {
                    include: 'vendor_credits_count,bills_count',
                    per_page: $scope.filter.per_page,
                    page: $scope.filter.page,
                },
                function (all, headers) {
                    $scope.loading--;
                    $scope.workflows = all;
                    let links = Core.parseLinkHeader(headers('Link'));
                    $scope.pageCount = links.last.match(/[\?\&]page=(\d+)/)[1];
                    $scope.total_count = headers('X-Total-Count');
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        };

        $scope.goToPage($scope.filter.page);
    }
})();
