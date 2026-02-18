/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('TaxRulesSettingsController', TaxRulesSettingsController);

    TaxRulesSettingsController.$inject = [
        '$scope',
        '$modal',
        'selectedCompany',
        'LeavePageWarning',
        'Core',
        'TaxRate',
        'TaxRule',
    ];

    function TaxRulesSettingsController($scope, $modal, selectedCompany, LeavePageWarning, Core, TaxRate, TaxRule) {
        $scope.rules = [];
        $scope.deleting = {};
        $scope.search = '';

        $scope.newRuleModal = function () {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-tax-rule.html',
                controller: 'EditTaxRuleController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    rule: function () {
                        return {
                            state: selectedCompany.state,
                            country: selectedCompany.country,
                        };
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
                templateUrl: 'catalog/views/edit-tax-rule.html',
                controller: 'EditTaxRuleController',
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
                message: 'Are you sure you want to delete this tax rule?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[rule.id] = true;
                        $scope.error = null;

                        TaxRule.delete(
                            {
                                id: rule.id,
                            },
                            function () {
                                $scope.deleting[rule.id] = false;

                                Core.flashMessage('The tax rule has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.rules) {
                                    if ($scope.rules[i].id == rule.id) {
                                        $scope.rules.splice(i, 1);
                                        break;
                                    }
                                }

                                // clear HTTP cache
                                // this is necessary until angular.js is updated to support
                                // transformResponse when the result is a 204
                                TaxRule.clearCache();
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

        Core.setTitle('Tax Rules');
        loadRules();

        function loadRules() {
            $scope.loading = true;
            TaxRule.all(
                function (rules) {
                    $scope.rules = rules;
                    loadRates(rules);
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadRates(rules) {
            if (rules.length === 0) {
                $scope.loading = false;
                return;
            }

            TaxRate.all(
                function (taxRates) {
                    let ratesMap = {};
                    angular.forEach(taxRates, function (taxRate) {
                        ratesMap[taxRate.id] = taxRate;
                    });

                    angular.forEach(rules, function (rule) {
                        rule.tax_rate = ratesMap[rule.tax_rate];
                    });
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
