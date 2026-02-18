(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('NewApprovalWorkflowController', NewApprovalWorkflowController);

    NewApprovalWorkflowController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        'LeavePageWarning',
        'selectedCompany',
        'Core',
        'ApprovalWorkflow',
        'Member',
        'Money',
        'Role',
    ];

    function NewApprovalWorkflowController(
        $scope,
        $state,
        $stateParams,
        LeavePageWarning,
        selectedCompany,
        Core,
        ApprovalWorkflow,
        Member,
        Money,
        Role,
    ) {
        let SPLIT = ' and ';
        let VARIABLE = 'document.total';
        $scope.workflow = {
            paths: [],
            name: '',
            enabled: true,
        };
        $scope.company = angular.copy(selectedCompany);
        $scope.loading = 0;
        $scope.saving = 0;
        $scope.workflowForm = {
            autoapprove: false,
        };
        $scope.invdlidParts = true;

        $scope.selectMembers = {
            data: [],
            placeholder: 'Select members',
            width: '100%',
        };

        $scope.selectRoles = {
            data: [],
            placeholder: 'Select roles',
            width: '100%',
        };

        $scope.isSinglePath = function () {
            return !$scope.workflow.paths[0].max_boundary && $scope.workflow.paths.length === 2;
        };

        ++$scope.loading;
        Member.all(
            function (members) {
                angular.forEach(members, function (member) {
                    $scope.selectMembers.data.push({
                        id: member.id,
                        text: formatMemberName(member),
                    });
                });
                --$scope.loading;
            },
            function (result) {
                Core.showMessage(result.data.message, 'error');
                --$scope.loading;
            },
        );
        ++$scope.loading;
        Role.all(
            {},
            function (roles) {
                angular.forEach(roles, function (role) {
                    $scope.selectRoles.data.push({
                        id: role.id,
                        text: role.name,
                    });
                });
                --$scope.loading;
            },
            function (result) {
                Core.showMessage(result.data.message, 'error');
                --$scope.loading;
            },
        );

        let calculateNextBoundary = function (boundary) {
            return Money.denormalizeFromZeroDecimal(
                $scope.company.currency,
                Money.normalizeToZeroDecimal($scope.company.currency, boundary) + 1,
            );
        };

        $scope.addPath = function () {
            let min = $scope.workflow.paths.length
                ? calculateNextBoundary($scope.workflow.paths[$scope.workflow.paths.length - 1].max_boundary)
                : 0;
            let max = null;

            let path = {
                min_boundary: min,
                max_boundary: max,
                steps: [],
            };
            $scope.workflow.paths.push(path);
            $scope.addStep(path);
        };

        $scope.addStep = function (path) {
            path.steps.push({
                minimum_approvers: 1,
                members: null,
                roles: null,
            });
        };

        $scope.removeCondition = function (key) {
            $scope.workflow.paths.splice(key, 1);
        };
        $scope.deleteStep = function (pathKey, stepKey) {
            $scope.workflow.paths[pathKey].steps.splice(stepKey, 1);
        };

        $scope.$watch(
            'workflow',
            function () {
                $scope.invdlidParts = false;
                angular.forEach($scope.workflow.paths, function (path, key) {
                    if (key + 1 < $scope.workflow.paths.length) {
                        $scope.workflow.paths[key + 1].min_boundary = calculateNextBoundary(path.max_boundary);
                        $scope.invdlidParts =
                            $scope.invdlidParts || (path.min_boundary >= path.max_boundary && !$scope.isSinglePath());
                    }
                    return true;
                });
            },
            true,
        );

        $scope.save = function () {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                default: $scope.workflow.default,
                enabled: $scope.workflow.enabled,
                name: $scope.workflow.name,
                paths: [],
            };

            if ($scope.isSinglePath()) {
                params.paths = [
                    {
                        id: $scope.workflow.paths[0].id,
                        rules: '',
                        steps: getSteps($scope.workflow.paths[0].steps),
                    },
                ];
            } else {
                angular.forEach($scope.workflow.paths, function (path, key) {
                    let query = '';
                    if (path.min_boundary) {
                        query += VARIABLE + ' > ' + path.min_boundary;
                        if (path.max_boundary) {
                            query += SPLIT;
                        }
                    }
                    if (path.max_boundary) {
                        query += VARIABLE + ' < ' + path.max_boundary;
                    }
                    params.paths[key] = {
                        id: path.id,
                        rules: query,
                        steps: getSteps(path.steps),
                    };
                });
            }

            if ($scope.workflowForm.autoapprove) {
                params.paths[0].steps = [
                    {
                        id: params.paths[0].steps[0].id,
                        minimum_approvers: 0,
                    },
                ];
            }

            if ($scope.workflow.id) {
                ApprovalWorkflow.edit(
                    {
                        id: $scope.workflow.id,
                    },
                    params,
                    function () {
                        $scope.saving = false;

                        Core.flashMessage('Your approval workflow has been updated.', 'success');

                        LeavePageWarning.unblock();
                        $state.go('manage.settings.approval_workflows');
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                ApprovalWorkflow.create(
                    params,
                    function () {
                        $scope.saving = false;

                        Core.flashMessage('Your approval workflow  has been created.', 'success');

                        LeavePageWarning.unblock();
                        $state.go('manage.settings.approval_workflows');
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        };

        LeavePageWarning.watchForm($scope, 'workflowForm');

        if ($stateParams.id) {
            Core.setTitle('Edit Approval Workflow');
            loadWorkflow($stateParams.id);
        } else {
            Core.setTitle('New Approval Workflow');
            $scope.addPath();
            $scope.addPath();
        }

        function getSteps(steps) {
            let result = [];
            angular.forEach(steps, function (step, stepKey) {
                result[stepKey] = {
                    id: step.id,
                    members: step.members.map(function (member) {
                        return member.id;
                    }),
                    minimum_approvers: step.minimum_approvers,
                    roles: step.roles.map(function (role) {
                        return role.id;
                    }),
                };
            });
            return result;
        }

        function formatMemberName(member) {
            return member.user.first_name + ' ' + member.user.last_name;
        }

        function loadWorkflow(id) {
            $scope.loading++;

            ApprovalWorkflow.find(
                {
                    id: id,
                    include: 'vendor_credits_count,bills_count,paths_list',
                    exclude: 'paths',
                },
                function (workflow) {
                    $scope.loading--;
                    workflow.paths = workflow.paths_list;
                    delete workflow.paths_list;
                    angular.forEach(workflow.paths, function (path, key) {
                        angular.forEach(path.steps, function (step, key2) {
                            angular.forEach(step.members, function (member, key3) {
                                workflow.paths[key].steps[key2].members[key3].text = formatMemberName(member);
                            });
                            angular.forEach(step.roles, function (role, key3) {
                                workflow.paths[key].steps[key2].roles[key3].text = role.name;
                            });
                        });
                        //reverse split rules
                        workflow.paths[key].min_boundary = 0;
                        let rules = path.rules.split(SPLIT);
                        for (let i in rules) {
                            if (rules[i].indexOf('>') > 0) {
                                workflow.paths[key].min_boundary = parseFloat(rules[i].replace(VARIABLE + ' > ', ''));
                            } else if (rules[i].indexOf('<') > 0) {
                                workflow.paths[key].max_boundary = parseFloat(rules[i].replace(VARIABLE + ' < ', ''));
                            }
                        }
                    });
                    if (workflow.paths[0].steps[0].minimum_approvers === 0) {
                        $scope.workflowForm.autoapprove = true;
                    }
                    $scope.workflow = workflow;
                    if (!workflow.paths[0].max_boundary) {
                        $scope.addPath();
                    }
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
