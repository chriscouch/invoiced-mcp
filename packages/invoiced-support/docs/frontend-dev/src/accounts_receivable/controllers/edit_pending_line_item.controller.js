(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('EditPendingLineItemController', EditPendingLineItemController);

    EditPendingLineItemController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'selectedCompany',
        'InvoiceCalculator',
        'Customer',
        'customer',
        'lineItem',
        'Feature',
    ];

    function EditPendingLineItemController(
        $scope,
        $modal,
        $modalInstance,
        selectedCompany,
        InvoiceCalculator,
        Customer,
        customer,
        lineItem,
        Feature,
    ) {
        $scope.customer = angular.copy(customer);

        $scope.company = selectedCompany;
        $scope.currency = customer.currency || selectedCompany.currency;
        $scope.hasFeature = Feature.hasFeature('metered_billing');

        if (lineItem) {
            $scope.line = angular.copy(lineItem);
            $scope.isExisting = true;
        } else {
            $scope.line = {
                name: '',
                description: '',
                type: null,
                quantity: 1,
                unit_cost: '',
                discounts: [],
                taxes: [],
                catalog_item: null,
            };
            $scope.isExisting = false;
        }

        let rateObjectKeys = {
            discount: 'coupon',
            tax: 'tax_rate',
        };

        calculateAmount();

        $scope.$watch('line', calculateAmount, true);

        $scope.addRateModal = function (rates, type) {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-rate.html',
                controller: 'AddRateController',
                resolve: {
                    currency: function () {
                        return $scope.currency;
                    },
                    ignore: function () {
                        return rates;
                    },
                    type: function () {
                        return type;
                    },
                    options: function () {
                        return {};
                    },
                },
                windowClass: 'add-rate-modal',
            });

            modalInstance.result.then(
                function (rate) {
                    addRate(rates, type, rate);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.selectItem = function (line) {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-item.html',
                controller: 'AddItemController',
                resolve: {
                    currency: function () {
                        return $scope.currency;
                    },
                    requireCurrency: function () {
                        return false;
                    },
                    multiple: function () {
                        return false;
                    },
                },
                windowClass: 'add-item-modal',
            });

            modalInstance.result.then(
                function (items) {
                    let item = items[0];
                    line.catalog_item = item.id;
                    line.name = item.name;
                    line.quantity = 1;
                    line.unit_cost = item.unit_cost;
                    line.type = item.type;
                    line.description = item.description;
                    line.discountable = item.discountable;
                    line.taxable = item.taxable;
                    line.taxes = item.taxes;
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.removeItem = function (line) {
            delete line.catalog_item;
        };

        $scope.save = function (line) {
            $scope.saving = true;
            $scope.error = null;

            line = angular.copy(line);

            // parse applied rates
            angular.forEach(['discounts', 'taxes'], function (type) {
                angular.forEach(line[type], function (appliedRate) {
                    if (appliedRate.id < 0) {
                        delete appliedRate.id;
                    }

                    delete appliedRate._amount;
                });
            });

            if ($scope.isExisting) {
                Customer.editLineItem(
                    {
                        customer: $scope.customer.id,
                    },
                    {
                        id: line.id,
                        name: line.name,
                        type: line.type,
                        description: line.description,
                        quantity: line.quantity,
                        unit_cost: line.unit_cost,
                        discounts: line.discounts,
                        taxes: line.taxes,
                    },
                    function (line) {
                        $scope.saving = false;

                        $modalInstance.close(line);
                    },
                    function (result) {
                        $scope.error = result.data;
                    },
                );
            } else {
                Customer.createLineItem(
                    {
                        customer: $scope.customer.id,
                    },
                    line,
                    function (line) {
                        $scope.saving = false;
                        $modalInstance.close(line);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function calculateAmount() {
            let invoice = {
                items: [$scope.line],
            };

            InvoiceCalculator.calculate(invoice, $scope.company.moneyFormat);

            $scope.amount = invoice.total;
        }

        function addRate(appliedRates, type, rate) {
            let k = rateObjectKeys[type];
            let appliedRate = {
                id: generateID(),
            };
            appliedRate[k] = rate;

            appliedRates.push(appliedRate);
        }

        function generateID() {
            // generate a unique ID for ngRepeat track by
            // (use negative #s to prevent collisions with
            //  actual Applied Rate IDs)
            return -1 * Math.round(Math.random() * 1000);
        }
    }
})();
