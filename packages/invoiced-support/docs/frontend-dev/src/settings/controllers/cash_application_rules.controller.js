/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('CashApplicationRulesController', CashApplicationRulesController);

    CashApplicationRulesController.$inject = [
        '$scope',
        '$modal',
        'selectedCompany',
        'LeavePageWarning',
        'Core',
        'CashApplicationRules',
    ];

    function CashApplicationRulesController(
        $scope,
        $modal,
        selectedCompany,
        LeavePageWarning,
        Core,
        CashApplicationRules,
    ) {
        $scope.rules = [];
        $scope.deleting = {};
        $scope.search = '';

        $scope.newRuleModal = function () {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'settings/views/edit-cash-application-rule.html',
                controller: 'EditCashApplicationRuleController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    rule: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function (newRule) {
                    LeavePageWarning.unblock();

                    $scope.rules.push(newRule);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.editRuleModal = function (rule) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'settings/views/edit-cash-application-rule.html',
                controller: 'EditCashApplicationRuleController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    rule: function () {
                        return rule;
                    },
                },
            });

            modalInstance.result.then(
                function (updatedRule) {
                    LeavePageWarning.unblock();

                    angular.extend(rule, updatedRule);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (rule) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this matching rule?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[rule.id] = true;
                        $scope.error = null;

                        CashApplicationRules.delete(
                            {
                                id: rule.id,
                            },
                            function () {
                                $scope.deleting[rule.id] = false;

                                Core.flashMessage('The matching rule has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.rules) {
                                    if ($scope.rules[i].id === rule.id) {
                                        $scope.rules.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                $scope.deleting[rule.id] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('Matching Rules');
        loadRules();

        function loadRules() {
            $scope.loading = true;
            CashApplicationRules.findAll(
                { paginate: 'none' },
                function (rules) {
                    $scope.rules = rules;
                    $scope.loading = false;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
