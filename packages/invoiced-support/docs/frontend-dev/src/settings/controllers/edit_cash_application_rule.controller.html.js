(function () {
    'use strict';

    angular.module('app.catalog').controller('EditCashApplicationRuleController', EditCashApplicationRuleController);

    EditCashApplicationRuleController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'selectedCompany',
        'CashApplicationRules',
        'rule',
    ];

    function EditCashApplicationRuleController(
        $scope,
        $modal,
        $modalInstance,
        selectedCompany,
        CashApplicationRules,
        rule,
    ) {
        $scope.rule = angular.copy(rule);
        $scope.isExisting = !!rule.id;
        $scope.customer = rule.customer;
        if (rule.ignore) {
            $scope.ignorePayment = 'ignore';
        } else {
            $scope.ignorePayment = 'set';
        }

        $scope.save = function (rule, customer) {
            $scope.saving = true;
            $scope.error = null;

            if ($scope.ignorePayment === 'ignore') {
                rule.ignore = 1;
                delete rule.method;
            } else if (customer) {
                rule.customer = customer.id;
            }

            if ($scope.isExisting) {
                saveExisting(rule);
            } else {
                saveNew(rule);
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function saveExisting(rule) {
            CashApplicationRules.edit(
                {
                    id: rule.id,
                },
                rule,
                function (rule) {
                    $scope.saving = false;
                    if ($scope.customer) {
                        rule.customerName = $scope.customer.name;
                    }
                    $modalInstance.close(rule);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function saveNew(rule) {
            CashApplicationRules.create(
                rule,
                function (rule) {
                    $scope.saving = false;
                    if ($scope.customer) {
                        rule.customerName = $scope.customer.name;
                    }
                    $modalInstance.close(rule);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
