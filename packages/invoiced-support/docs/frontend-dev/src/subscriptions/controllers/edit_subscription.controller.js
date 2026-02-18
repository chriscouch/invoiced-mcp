/* globals _, moment */
(function () {
    'use strict';

    angular.module('app.subscriptions').controller('EditSubscriptionController', EditSubscriptionController);

    EditSubscriptionController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        '$timeout',
        'Subscription',
        'CustomField',
        'Settings',
        'selectedCompany',
        'SubscriptionCalculator',
        'Core',
        'MetadataCaster',
        'Customer',
        'subscription',
        'Feature',
        'DatePickerService',
        'PaymentDisplayHelper',
    ];

    function EditSubscriptionController(
        $scope,
        $modalInstance,
        $modal,
        $timeout,
        Subscription,
        CustomField,
        Settings,
        selectedCompany,
        SubscriptionCalculator,
        Core,
        MetadataCaster,
        Customer,
        subscription,
        Feature,
        DatePickerService,
        PaymentDisplayHelper,
    ) {
        $scope.company = selectedCompany;

        $scope.subscription = angular.copy(subscription);
        $scope.prorate = true;
        $scope.original = subscription;
        $scope.prorateNow = true;
        $scope.prorationDate = new Date();
        $scope.supportsItems = Feature.hasFeature('recurring_catalog_items');
        $scope.paymentSources = [];
        $scope.useDefaultPaymentSource = subscription.payment_source === null;
        $scope.paymentSource = subscription.payment_source;

        $scope.dateOptions = DatePickerService.getOptions({
            // cannot select a proration date outside of the subscription period
            minDate: moment.unix(subscription.period_start).toDate(),
            maxDate: moment.unix(subscription.period_end).toDate(),
        });

        $scope.planTotal = 0;
        $scope.subscriptionTotal = 0;

        let loadedPaymentSources = false;

        let memoizedPreview = _.memoize(Subscription.preview, function (args) {
            return angular.toJson(args);
        });

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            $timeout(function () {
                /* CSS Z-indexing Overrides */
                $('#ui-datepicker-div').css('z-index', '9999');
            }, 100);
        };

        $scope.addItem = function () {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-item.html',
                controller: 'AddItemController',
                resolve: {
                    currency: function () {
                        if (typeof $scope.subscription.plan == 'object') {
                            return $scope.subscription.plan.currency;
                        } else {
                            return $scope.company.currency;
                        }
                    },
                    requireCurrency: function () {
                        return true;
                    },
                    multiple: function () {
                        return true;
                    },
                },
                backdrop: 'static',
                keyboard: false,
                windowClass: 'add-item-modal',
            });

            modalInstance.result.then(
                function (items) {
                    // add each item as a new addon
                    angular.forEach(items, function (item) {
                        let addon = {
                            catalog_item: item,
                            quantity: 1,
                        };

                        $scope.subscription.addons.push(addon);
                    });
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.addPlan = function () {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-plan.html',
                controller: 'AddPlanController',
                resolve: {
                    currency: function () {
                        return $scope.subscription.plan.currency;
                    },
                    interval: function () {
                        return $scope.subscription.plan.interval;
                    },
                    interval_count: function () {
                        return $scope.subscription.plan.interval_count;
                    },
                    multiple: function () {
                        return true;
                    },
                    filter: function () {
                        return function () {
                            return true;
                        };
                    },
                },
                backdrop: 'static',
                keyboard: false,
                windowClass: 'add-plan-modal',
            });

            modalInstance.result.then(
                function (plans) {
                    // add each plan as a new addon
                    angular.forEach(plans, function (plan) {
                        let addon = {
                            plan: plan,
                            quantity: 1,
                        };

                        $scope.subscription.addons.push(addon);
                    });

                    SubscriptionCalculator.getAddonTotals($scope, memoizedPreview);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.deleteAddon = function (addon) {
            let i = 0;
            for (i in $scope.subscription.addons) {
                let addon2 = $scope.subscription.addons[i];
                if (addon.catalog_item && addon2.catalog_item == addon.catalog_item) {
                    break;
                }
                if (addon.plan && addon2.plan == addon.plan) {
                    break;
                }
            }

            $scope.subscription.addons.splice(i, 1);
        };

        $scope.deleteDiscount = function (discounts, index) {
            discounts.splice(index, 1);
        };

        $scope.deleteTax = function (taxes, index) {
            taxes.splice(index, 1);
        };

        $scope.selectRatesModal = function (rates, type) {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-rate.html',
                controller: 'AddRateController',
                resolve: {
                    currency: function () {
                        if (typeof $scope.subscription.plan == 'object') {
                            return $scope.subscription.plan.currency;
                        } else {
                            return $scope.company.currency;
                        }
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
                    rates.push(rate);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.canProrate = function (a, b) {
            // check plan and quantity
            if (a.quantity != b.quantity || a.plan.id != b.plan.id) {
                return true;
            }

            // check amount
            if (a.amount != b.amount) {
                return true;
            }

            // check # of addons
            if (a.addons.length != b.addons.length) {
                return true;
            }

            // check addon items/quantities
            let changed = false;
            angular.forEach(a.addons, function (addon, index) {
                let addon2 = b.addons[index];
                if (
                    (addon.catalog_item && addon.catalog_item.id != addon2.catalog_item.id) ||
                    (addon.plan && addon.plan.id != addon2.plan.id) ||
                    addon.quantity != addon2.quantity ||
                    addon.amount != addon2.amount
                ) {
                    changed = true;
                }
            });

            return changed;
        };

        $scope.addShipping = function (subscription) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/add-shipping.html',
                controller: 'AddShippingController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    shipTo: function () {
                        return subscription.ship_to;
                    },
                    customer: function () {
                        return subscription.customer;
                    },
                },
            });

            modalInstance.result.then(
                function (shipTo) {
                    subscription.ship_to = shipTo;
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.save = function (subscription, prorate) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                prorate: prorate,
            };

            if (prorate && !$scope.prorateNow) {
                params.proration_date = moment($scope.prorationDate).startOf('day').unix();
            }

            // has the amount changed?
            if ($scope.original.amount != subscription.amount) {
                params.amount = subscription.amount;
            }

            // has the plan changed?
            if ($scope.original.plan.id != subscription.plan.id) {
                params.plan = subscription.plan.id;
                if ('custom' === subscription.plan.pricing_mode) {
                    params.amount = subscription.amount;
                } else {
                    params.amount = null;
                }
            }

            // has the quantity changed?
            if ($scope.original.quantity != subscription.quantity) {
                params.quantity = subscription.quantity;
            }

            // has the description changed?
            if ($scope.original.description != subscription.description) {
                params.description = subscription.description;
            }

            // parse rates
            params.discounts = [];
            params.taxes = [];
            angular.forEach(['discounts', 'taxes'], function (type) {
                angular.forEach(subscription[type], function (rate) {
                    params[type].push(rate.id);
                });
            });

            // parse addons
            params.addons = [];
            angular.forEach(subscription.addons, function (addon) {
                let _addon = angular.copy(addon);
                if (addon.catalog_item) {
                    _addon.catalog_item = addon.catalog_item.id;
                }
                if (addon.plan) {
                    _addon.plan = addon.plan.id;
                }
                params.addons.push(_addon);
            });

            // has the ship to changed?
            if (!angular.equals($scope.original.ship_to, subscription.ship_to)) {
                params.ship_to = subscription.ship_to;
            }

            // parse payment method
            if (!$scope.useDefaultPaymentSource) {
                params.payment_source_type = $scope.paymentSource.object;
                params.payment_source_id = $scope.paymentSource.id;
            } else {
                params.payment_source_type = null;
                params.payment_source_id = null;
            }

            // parse metadata
            MetadataCaster.marshalForInvoiced('subscription', subscription.metadata, function (metadata) {
                params.metadata = metadata;

                Subscription.edit(
                    {
                        id: subscription.id,
                    },
                    params,
                    function (result) {
                        $scope.saving = false;

                        // use our locally cached version of these object
                        // because they are not included or expanded in the response
                        result.customer = subscription.customer;
                        result.plan = subscription.plan;
                        result.addons = subscription.addons;
                        result.ship_to = subscription.ship_to;

                        $modalInstance.close(result);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            });
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.differentCycle = false;
        $scope.$watch(
            'subscription',
            function (subscription) {
                if (subscription.customer.autopay && !subscription.customer.payment_source) {
                    $scope.starts = 'request';
                } else {
                    $scope.starts = 'on_date';
                }

                // reset amount on non-custom plans because the field is hidden
                if (subscription.plan.pricing_mode !== 'custom') {
                    subscription.amount = null;
                }

                $scope.differentCycle =
                    subscription.plan.interval != $scope.original.plan.interval ||
                    subscription.plan.interval_count != $scope.original.plan.interval_count;

                // Subscription Total
                SubscriptionCalculator.preview(subscription, $scope, memoizedPreview);

                // Plan total
                SubscriptionCalculator.getPlanTotal(subscription, $scope, memoizedPreview);
            },
            true,
        );

        $scope.$watch(
            function () {
                return $scope.subscription.addons;
            },
            function (addons, old) {
                if (!angular.equals(addons, old)) {
                    angular.forEach($scope.subscription.addons, function (addon, index) {
                        if (addon.plan && old[index] && addon.quantity !== old[index].quantity) {
                            let result = memoizedPreview(
                                {
                                    plan: addon.plan.id,
                                    amount: addon.plan.amount,
                                    tiers: addon.plan.tiers,
                                    quantity: addon.quantity,
                                },
                                function (preview) {
                                    $scope.subscription.addons[index].total = preview.recurring_total;
                                },
                                function () {
                                    // do nothing
                                },
                            );

                            if (result) {
                                $scope.subscription.addons[index].total = result.recurring_total;
                            }
                        }
                    });
                }
            },
            true,
        );

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        loadCustomFields();
        loadSettings();
        loadPaymentSources(subscription.customer);
        SubscriptionCalculator.getAddonTotals($scope, memoizedPreview);

        function loadCustomFields() {
            CustomField.all(
                function (customFields) {
                    $scope.customFields = [];
                    angular.forEach(customFields, function (customField) {
                        if (customField.object === 'subscription') {
                            $scope.customFields.push(customField);
                        }
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadSettings() {
            Settings.accountsReceivable(
                function (settings) {
                    $scope.settings = settings;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadPaymentSources(customer) {
            if (loadedPaymentSources || !customer.autopay) {
                return;
            }

            Customer.paymentSources(
                {
                    id: customer.id,
                },
                function (paymentSources) {
                    angular.forEach(paymentSources, function (source) {
                        source.name = PaymentDisplayHelper.format(source);
                        if (
                            $scope.paymentSource &&
                            source.object == $scope.paymentSource.object &&
                            source.id == $scope.paymentSource.id
                        ) {
                            $scope.paymentSource = source;
                        }
                    });

                    $scope.paymentSources = paymentSources;
                    loadedPaymentSources = true;
                },
            );
        }
    }
})();
