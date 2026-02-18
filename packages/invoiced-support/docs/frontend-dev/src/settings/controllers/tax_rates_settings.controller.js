/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('TaxRatesSettingsController', TaxRatesSettingsController);

    TaxRatesSettingsController.$inject = ['$scope', '$modal', 'LeavePageWarning', 'Core', 'selectedCompany', 'TaxRate'];

    function TaxRatesSettingsController($scope, $modal, LeavePageWarning, Core, selectedCompany, TaxRate) {
        $scope.company = angular.copy(selectedCompany);

        $scope.taxRates = [];
        $scope.deleting = {};
        $scope.search = '';

        $scope.newTaxRateModal = function () {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-rate.html',
                controller: 'EditRateController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        return {
                            id: '',
                            name: '',
                            is_percent: true,
                            currency: selectedCompany.currency,
                            value: '',
                            inclusive: false,
                            metadata: {},
                        };
                    },
                    type: function () {
                        return 'tax_rate';
                    },
                },
            });

            modalInstance.result.then(
                function (newTaxRate) {
                    LeavePageWarning.unblock();

                    $scope.taxRates.push(newTaxRate);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.editTaxRateModal = function (taxRate) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-rate.html',
                controller: 'EditRateController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        return taxRate;
                    },
                    type: function () {
                        return 'tax_rate';
                    },
                },
            });

            modalInstance.result.then(
                function (updatedTaxRate) {
                    LeavePageWarning.unblock();

                    angular.extend(taxRate, updatedTaxRate);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (taxRate) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this tax rate?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[taxRate.id] = true;
                        $scope.error = null;

                        TaxRate.delete(
                            {
                                id: taxRate.id,
                            },
                            function () {
                                $scope.deleting[taxRate.id] = false;

                                Core.flashMessage('The tax rate, ' + taxRate.name + ', has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.taxRates) {
                                    if ($scope.taxRates[i].id == taxRate.id) {
                                        $scope.taxRates.splice(i, 1);
                                        break;
                                    }
                                }

                                // clear HTTP cache
                                // this is necessary until angular.js is updated to support
                                // transformResponse when the result is a 204
                                TaxRate.clearCache();
                            },
                            function (result) {
                                $scope.deleting[taxRate.id] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('Tax Rates');
        loadTaxRates();

        function loadTaxRates() {
            $scope.loading = true;
            TaxRate.all(
                function (taxRates) {
                    $scope.loading = false;
                    $scope.taxRates = taxRates;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
