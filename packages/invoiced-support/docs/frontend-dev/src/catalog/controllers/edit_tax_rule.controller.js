(function () {
    'use strict';

    angular.module('app.catalog').controller('EditTaxRuleController', EditTaxRuleController);

    EditTaxRuleController.$inject = ['$scope', '$modal', '$modalInstance', 'selectedCompany', 'TaxRule', 'rule'];

    function EditTaxRuleController($scope, $modal, $modalInstance, selectedCompany, TaxRule, rule) {
        $scope.rule = angular.copy(rule);
        $scope.isExisting = !!rule.id;
        $scope.appliesTo = 'all';
        if (rule.id && rule.state) {
            $scope.appliesTo = 'state';
        } else if (rule.id && rule.country) {
            $scope.appliesTo = 'country';
        }

        $scope.selectTaxRate = function (rule) {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-rate.html',
                controller: 'AddRateController',
                resolve: {
                    currency: function () {
                        return false;
                    },
                    ignore: function () {
                        return [];
                    },
                    type: function () {
                        return 'tax';
                    },
                    options: function () {
                        return {};
                    },
                },
                windowClass: 'add-rate-modal',
            });

            modalInstance.result.then(
                function (rate) {
                    rule.tax_rate = rate;
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.save = function (rule, appliesTo) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                tax_rate: rule.tax_rate.id,
                country: null,
                state: null,
            };

            if (appliesTo === 'state') {
                params.state = rule.state;
                params.country = rule.country;
            } else if (appliesTo === 'country') {
                params.country = rule.country;
            }

            if ($scope.isExisting) {
                saveExisting(rule.id, params, rule.tax_rate);
            } else {
                saveNew(params, rule.tax_rate);
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function saveExisting(id, params, taxRate) {
            TaxRule.edit(
                {
                    id: id,
                },
                params,
                function (rule) {
                    $scope.saving = false;
                    rule.tax_rate = taxRate;
                    $modalInstance.close(rule);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function saveNew(params, taxRate) {
            TaxRule.create(
                params,
                function (rule) {
                    $scope.saving = false;

                    rule.tax_rate = taxRate;
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
