(function () {
    'use strict';

    angular.module('app.catalog').controller('EditPlanController', EditPlanController);

    EditPlanController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        'Plan',
        'IdGenerator',
        'CustomField',
        'MetadataCaster',
        'InvoicedConfig',
        'Core',
        'plan',
        'Feature',
    ];

    function EditPlanController(
        $scope,
        $modalInstance,
        $modal,
        Plan,
        IdGenerator,
        CustomField,
        MetadataCaster,
        InvoicedConfig,
        Core,
        plan,
        Feature,
    ) {
        $scope.plan = angular.copy(plan);
        $scope.isExisting = !!plan.id;
        $scope.shouldGenID = true;
        $scope.changePricing = false;
        $scope.hadDescription = plan.id && plan.description;
        $scope.hasMultiCurrency = Feature.hasFeature('multi_currency');
        $scope.recurs = determineRecurs(plan.interval_count, plan.interval);

        loadCustomFields();

        $scope.generateID = function (model) {
            if ((!$scope.shouldGenID && model.id) || $scope.isExisting) {
                return;
            }

            $scope.shouldGenID = true;
            if (!model.name) {
                model.id = '';
                return;
            }

            // generate ID as the user types the name
            // i.e. Invoiced Pro -> invoiced-pro
            model.id = IdGenerator.generate(model.name);
        };

        $scope.selectItem = function (plan) {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-item.html',
                controller: 'AddItemController',
                resolve: {
                    currency: function () {
                        return plan.currency;
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
                    plan.catalog_item = items[0];
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.addTier = function (plan) {
            if (plan.tiers === null) {
                plan.tiers = [];
            }

            let length = plan.tiers.length;
            if (length === 0) {
                plan.tiers.push({
                    min_qty: 0,
                    max_qty: null,
                    unit_cost: null,
                });

                plan.tiers.push({
                    min_qty: null,
                    unit_cost: null,
                });

                return;
            }

            plan.tiers.push({
                min_qty: plan.tiers[length - 2].max_qty + 1,
                max_qty: null,
                unit_cost: null,
            });
        };

        $scope.updateTier = function (plan, index) {
            let prevMax = plan.tiers[index].max_qty;
            if (undefined === prevMax || null == prevMax) {
                plan.tiers[index + 1].min_qty = null;
            } else {
                plan.tiers[index + 1].min_qty = prevMax + 1;
            }
        };

        $scope.deleteTier = function (plan, index) {
            plan.tiers.splice(index, 1);
            $scope.updateTier(plan, index - 1);
        };

        $scope.save = function (plan) {
            $scope.saving = true;
            plan = angular.copy(plan);

            if (plan.catalog_item && typeof plan.catalog_item === 'object') {
                plan.catalog_item = plan.catalog_item.id;
            }

            // parse interval
            if ($scope.recurs === 'daily') {
                plan.interval = 'day';
                plan.interval_count = 1;
            } else if ($scope.recurs === 'weekly') {
                plan.interval = 'week';
                plan.interval_count = 1;
            } else if ($scope.recurs === 'monthly') {
                plan.interval = 'month';
                plan.interval_count = 1;
            } else if ($scope.recurs === 'quarterly') {
                plan.interval = 'month';
                plan.interval_count = 3;
            } else if ($scope.recurs === 'semiannual') {
                plan.interval = 'month';
                plan.interval_count = 6;
            } else if ($scope.recurs === 'yearly') {
                plan.interval = 'year';
                plan.interval_count = 1;
            }

            // pricing mode
            if (plan.pricing_mode === 'per_unit') {
                plan.tiers = null;
            } else if (plan.pricing_mode === 'volume' || plan.pricing_mode === 'tiered') {
                plan.amount = 0;
            } else if (plan.pricing_mode === 'custom') {
                plan.tiers = null;
                plan.amount = null;
            }

            // parse metadata
            MetadataCaster.marshalForInvoiced('plan', plan.metadata, function (metadata) {
                plan.metadata = metadata;

                if ($scope.isExisting) {
                    if ($scope.changePricing) {
                        deleteAndCreate(plan);
                    } else {
                        saveExisting(plan);
                    }
                } else {
                    saveNew({
                        id: plan.id,
                        name: plan.name,
                        currency: plan.currency,
                        amount: plan.amount,
                        pricing_mode: plan.pricing_mode,
                        tiers: plan.tiers,
                        interval: plan.interval,
                        interval_count: plan.interval_count,
                        description: plan.description,
                        notes: plan.notes,
                        catalog_item: plan.catalog_item,
                        metadata: plan.metadata,
                    });
                }
            });
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        if (!(typeof $scope.plan.tiers === 'object' && $scope.plan.tiers instanceof Array)) {
            $scope.plan.tiers = [];
        }

        if ($scope.plan.tiers.length === 0) {
            $scope.addTier($scope.plan);
        }

        function saveExisting(plan) {
            Plan.edit(
                {
                    id: plan.id,
                },
                {
                    name: plan.name,
                    description: plan.description,
                    catalog_item: plan.catalog_item,
                    metadata: plan.metadata,
                },
                function (_plan) {
                    $scope.saving = false;
                    $modalInstance.close(_plan);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function deleteAndCreate(plan) {
            Plan.delete(
                {
                    id: plan.id,
                },
                function () {
                    saveNew({
                        id: plan.id,
                        name: plan.name,
                        currency: plan.currency,
                        amount: plan.amount,
                        pricing_mode: plan.pricing_mode,
                        tiers: plan.tiers,
                        interval: plan.interval,
                        interval_count: plan.interval_count,
                        description: plan.description,
                        notes: plan.notes,
                        catalog_item: plan.catalog_item,
                        metadata: plan.metadata,
                    });
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function saveNew(plan) {
            Plan.create(
                {},
                plan,
                function (_plan) {
                    $scope.saving = false;
                    $modalInstance.close(_plan);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                    $scope.isExisting = false;
                },
            );
        }

        function determineRecurs(count, interval) {
            if (!count && !interval) {
                return 'monthly';
            }

            if (count == 1 && interval === 'day') {
                return 'daily';
            }

            if (count == 1 && interval === 'week') {
                return 'weekly';
            }

            if (count == 1 && interval === 'month') {
                return 'monthly';
            }

            if (count == 3 && interval === 'month') {
                return 'quarterly';
            }

            if (count == 6 && interval === 'month') {
                return 'semiannual';
            }

            if (count == 1 && interval === 'year') {
                return 'yearly';
            }

            return 'custom';
        }

        function loadCustomFields() {
            CustomField.all(
                function (customFields) {
                    $scope.customFields = [];
                    angular.forEach(customFields, function (customField) {
                        // All type custom fields are intentionally excluded here
                        if (customField.object === 'plan') {
                            $scope.customFields.push(customField);
                        }
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
