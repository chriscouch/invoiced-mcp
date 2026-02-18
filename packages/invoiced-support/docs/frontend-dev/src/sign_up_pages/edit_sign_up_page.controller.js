(function () {
    'use strict';

    angular.module('app.sign_up_pages').controller('EditSignUpPageController', EditSignUpPageController);

    EditSignUpPageController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        '$timeout',
        'selectedCompany',
        'Core',
        'SignUpPage',
        'SignUpPageAddon',
        'CustomField',
        'page',
        'Feature',
    ];

    function EditSignUpPageController(
        $scope,
        $modalInstance,
        $modal,
        $timeout,
        selectedCompany,
        Core,
        SignUpPage,
        SignUpPageAddon,
        CustomField,
        page,
        Feature,
    ) {
        $scope.hasSubscriptions = Feature.hasFeature('subscriptions');

        if (page) {
            $scope.page = angular.copy(page);
            $scope.hasTrial = !!page.trial_period_days;
            $scope.hasHeaderText = !!page.header_text;
            $scope.hasToS = !!page.tos_url;
            $scope.hasThankYou = !!page.thanks_url;
            $scope.calendarBilling = page.snap_to_nth_day > 0;
        } else {
            $scope.page = {
                id: '',
                name: '',
                header_text: null,
                tos_url: null,
                thanks_url: null,
                plans: [],
                taxes: [],
                custom_fields: [],
                trial_period_days: 0,
                has_quantity: false,
                has_coupon_code: false,
            };

            if ($scope.hasSubscriptions) {
                $scope.page.type = 'recurring';
            } else {
                $scope.page.type = 'autopay';
            }

            $scope.newPlanLine = true;
            $scope.hasTrial = false;
            $scope.hasHeaderText = false;
            $scope.hasToS = false;
            $scope.hasThankYou = false;
            $scope.calendarBilling = false;
        }

        $scope.addons = [];
        let originalAddons = [];

        $scope.company = selectedCompany;
        $scope.currency = $scope.company.currency;
        $scope.isExisting = !!page.id;

        $scope.sortableOptions = {
            handle: '.sortable-handle',
            placholder: 'sortable-placeholder',
        };

        $scope.rateListOptions = {
            types: false,
        };

        $scope.nthDayOptions = [];
        for (let i = 1; i <= 31; i++) {
            $scope.nthDayOptions.push({ name: ordinal_suffix_of(i), value: i });
        }

        // Remove custom plans from options of plans
        // to create sign up pages with.
        $scope.plansFilter = function (plan) {
            return plan.pricing_mode !== 'custom';
        };

        $scope.triggerNewPlan = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = false;
            $timeout(function () {
                $scope[name] = true;
            });
        };

        $scope.deletePlan = function (page, plan) {
            for (let i in page.plans) {
                if (page.plans[i].id == plan.id) {
                    page.plans.splice(i, 1);
                    break;
                }
            }

            if (page.plans.length === 0) {
                $scope.newPlanLine = true;
            }
        };

        $scope.addRecurringAddon = function (page) {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-plan.html',
                controller: 'AddPlanController',
                resolve: {
                    currency: function () {
                        if (page.plans.length > 0) {
                            return page.plans[0].currency;
                        } else {
                            return $scope.company.currency;
                        }
                    },
                    interval: function () {
                        return null;
                    },
                    interval_count: function () {
                        return null;
                    },
                    multiple: function () {
                        return true;
                    },
                    filter: function () {
                        return function (plan) {
                            return plan.pricing_mode != 'custom';
                        };
                    },
                },
                backdrop: 'static',
                keyboard: false,
                windowClass: 'add-plan-modal',
            });

            modalInstance.result.then(
                function (plans) {
                    angular.forEach(plans, function (plan) {
                        $scope.addons.push({
                            plan: plan,
                            has_quantity: false,
                            required: false,
                            recurring: true,
                        });
                    });
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.addOneTimeAddon = function (page) {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-item.html',
                controller: 'AddItemController',
                resolve: {
                    currency: function () {
                        if (page.plans.length > 0) {
                            return page.plans[0].currency;
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
                windowClass: 'add-item-modal',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function (items) {
                    angular.forEach(items, function (item) {
                        $scope.addons.push({
                            catalog_item: item,
                            has_quantity: false,
                            required: false,
                            recurring: false,
                        });
                    });
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.removeAddon = function (page, i) {
            $scope.addons.splice(i, 1);
        };

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
                    rates.push(rate);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.addCustomField = function (page) {
            const modalInstance = $modal.open({
                templateUrl: 'metadata/views/add-custom-field.html',
                controller: 'AddCustomFieldController',
                windowClass: 'add-custom-field-modal',
                size: 'sm',
                resolve: {
                    type: function () {
                        if ($scope.page.type == 'autopay') {
                            return 'customer';
                        }

                        return 'subscription';
                    },
                },
            });

            modalInstance.result.then(
                function (customField) {
                    // prevent duplicates
                    for (let i in page.custom_fields) {
                        if (page.custom_fields[i].id == customField.id) {
                            return;
                        }
                    }

                    page.custom_fields.push(customField);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.deleteCustomField = function (page, customField) {
            for (let i in page.custom_fields) {
                if (page.custom_fields[i].id == customField.id) {
                    page.custom_fields.splice(i, 1);
                    break;
                }
            }
        };

        $scope.save = function (page) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                type: page.type,
                name: page.name,
                billing_address: page.billing_address,
                shipping_address: page.shipping_address,
            };

            if (params.type === 'recurring') {
                let plans = [];
                angular.forEach(page.plans, function (plan) {
                    plans.push(plan.id);
                });
                params.plans = plans;

                let taxes = [];
                angular.forEach(page.taxes, function (tax) {
                    taxes.push(tax.id);
                });
                params.taxes = taxes;

                if (!$scope.hasTrial) {
                    params.trial_period_days = 0;
                } else {
                    params.trial_period_days = page.trial_period_days;
                }

                if (!$scope.calendarBilling) {
                    params.snap_to_nth_day = null;
                } else {
                    params.snap_to_nth_day = page.snap_to_nth_day;
                }

                params.has_quantity = page.has_quantity;
                params.has_coupon_code = page.has_coupon_code;
                params.allow_multiple_subscriptions = page.allow_multiple_subscriptions;
            } else if (params.type === 'autopay') {
                params.plans = [];
                params.taxes = [];
                params.trial_period_days = 0;
                params.snap_to_nth_day = false;
                params.has_quantity = false;
                params.has_coupon_code = false;
                params.allow_multiple_subscriptions = false;
            }

            let customFields = [];
            angular.forEach(page.custom_fields, function (customField) {
                customFields.push(customField.id);
            });
            params.custom_fields = customFields;

            if (!$scope.hasHeaderText) {
                params.header_text = null;
            } else {
                params.header_text = page.header_text;
            }

            if (!$scope.hasToS) {
                params.tos_url = null;
            } else {
                params.tos_url = page.tos_url;
            }

            if (!$scope.hasThankYou) {
                params.thanks_url = null;
            } else {
                params.thanks_url = page.thanks_url;
            }

            if ($scope.isExisting) {
                SignUpPage.edit(
                    {
                        id: page.id,
                    },
                    params,
                    function (_page) {
                        updateAddons(_page, $scope.addons);
                    },
                    function (result) {
                        $scope.error = result.data;
                    },
                );
            } else {
                SignUpPage.create(
                    {},
                    params,
                    function (_page) {
                        $scope.saving = false;
                        updateAddons(_page, $scope.addons);
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

        $scope.$watch('newPlan', function (plan) {
            if (typeof plan === 'object' && plan) {
                addPlan($scope.page, plan);
            }
        });

        if ($scope.page.id) {
            loadAddons($scope.page.id);
        }

        function loadAddons(id) {
            $scope.loading = true;
            SignUpPageAddon.findAll(
                {
                    'filter[sign_up_page_id]': id,
                    sort: 'order ASC',
                    paginate: 'none',
                },
                function (addons) {
                    $scope.loading = false;
                    angular.forEach(addons, function (addon) {
                        addon.has_quantity = addon.type === 'quantity';
                    });
                    $scope.addons = addons;
                    originalAddons = angular.copy(addons);
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function updateAddons(page, addons) {
            // delete all original addons
            deleteAllAddons(originalAddons, function () {
                // then we can save the new addons
                saveAddons(page, addons, function () {
                    $scope.saving = false;
                    $modalInstance.close(page);
                });
            });
        }

        function saveAddons(page, addons, cb) {
            if (addons.length === 0) {
                cb();
            }

            let saved = 0;
            let order = 0;
            angular.forEach(addons, function (addon) {
                let params = {
                    sign_up_page: page.id,
                    type: addon.has_quantity ? 'quantity' : 'boolean',
                    required: addon.required,
                    order: order,
                };

                order++;

                if (addon.plan) {
                    params.recurring = true;
                    params.plan = addon.plan.id;
                } else {
                    params.recurring = addon.recurring;
                    params.catalog_item = addon.catalog_item.id;
                }

                SignUpPageAddon.create(
                    params,
                    function () {
                        saved++;
                        if (saved === addons.length) {
                            cb();
                        }
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            });
        }

        function deleteAllAddons(addons, cb) {
            if (addons.length === 0) {
                cb();
            }

            let deleted = 0;
            angular.forEach(addons, function (addon) {
                SignUpPageAddon.delete(
                    {
                        id: addon.id,
                    },
                    function () {
                        deleted++;
                        if (deleted === addons.length) {
                            cb();
                        }
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            });
        }

        function addPlan(page, plan) {
            if (!plan) {
                return;
            }

            // prevent duplicates
            for (let i in page.plans) {
                if (page.plans[i].id == plan.id) {
                    return;
                }
            }

            page.plans.push(plan);

            // clear out selected plan
            $scope.newPlan = null;
            $scope.newPlanLine = false;
        }

        // Found on: http://stackoverflow.com/questions/13627308/add-st-nd-rd-and-th-ordinal-suffix-to-a-number#13627586
        function ordinal_suffix_of(i) {
            let j = i % 10,
                k = i % 100;
            if (j == 1 && k != 11) {
                return i + 'st';
            }
            if (j == 2 && k != 12) {
                return i + 'nd';
            }
            if (j == 3 && k != 13) {
                return i + 'rd';
            }
            return i + 'th';
        }
    }
})();
