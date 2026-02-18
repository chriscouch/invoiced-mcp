/* globals _, moment */
(function () {
    'use strict';

    angular.module('app.subscriptions').controller('NewSubscriptionController', NewSubscriptionController);

    NewSubscriptionController.$inject = [
        '$scope',
        '$rootScope',
        '$modalInstance',
        '$modal',
        '$timeout',
        'Subscription',
        'CustomField',
        'Settings',
        'Customer',
        'selectedCompany',
        'Core',
        'SubscriptionCalculator',
        'MetadataCaster',
        'customer',
        'Feature',
        'DatePickerService',
        'PaymentDisplayHelper',
    ];

    function NewSubscriptionController(
        $scope,
        $rootScope,
        $modalInstance,
        $modal,
        $timeout,
        Subscription,
        CustomField,
        Settings,
        Customer,
        selectedCompany,
        Core,
        SubscriptionCalculator,
        MetadataCaster,
        customer,
        Feature,
        DatePickerService,
        PaymentDisplayHelper,
    ) {
        $scope.company = selectedCompany;
        $scope.hasFeature = Feature.hasFeature('subscription_billing');
        $scope.supportsItems = Feature.hasFeature('recurring_catalog_items');

        $scope.subscription = {
            customer: customer,
            plan: null,
            start_date: null,
            quantity: 1,
            description: null,
            addons: [],
            discounts: [],
            taxes: [],
            snap_to_nth_day: 1,
            contract_renewal_mode: 'auto',
            ship_to: null,
            metadata: {},
            paused: false,
            bill_in: 'advance',
            bill_in_advance_days: 0,
            amount: null,
        };

        $scope.hasCustomer = !!customer;
        $scope.needsOnboarding = false;
        $scope.startsNow = true;
        $scope.sameRenewalLength = true;
        $scope.billOn = 'anniversary';
        $scope.prorateFirstBill = true;
        $scope.showPreview = false;
        $scope.paymentSources = [];
        $scope.useDefaultPaymentSource = true;
        $scope.contractIntervalCount = 1;
        $scope.contractInterval = 'month';
        $scope.contractRenewalIntervalCount = 1;
        $scope.contractRenewalInterval = 'month';
        $scope.showStartDateWarning = false;

        $scope.subscriptionTotal = 0;
        $scope.planTotal = 0;

        $scope.dateOptions = DatePickerService.getOptions({
            // cannot select a start date more than 5 years in the past
            minDate: -1825,
        });

        let memoizedPreview = _.memoize(Subscription.preview, function (args) {
            return angular.toJson(args);
        });

        let loadedPaymentSources = false;

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            $timeout(function () {
                /* CSS Z-indexing Overrides */
                $('#ui-datepicker-div').css('z-index', '9999');
            }, 100);
        };

        $scope.newPlan = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = false;
            $timeout(function () {
                $scope[name] = true;
            });
        };

        $scope.addItem = function () {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-item.html',
                controller: 'AddItemController',
                resolve: {
                    currency: function () {
                        if ($scope.subscription.plan) {
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
                            description: '',
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
                            description: '',
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
                if (addon.catalog_item && addon2.catalog_item === addon.catalog_item) {
                    break;
                }
                if (addon.plan && addon2.plan === addon.plan) {
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
                        if ($scope.subscription.plan) {
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

        $scope.create = function (model) {
            $scope.saving = true;
            $scope.error = null;

            let subscription = angular.copy(model);

            // parse customer
            if (typeof subscription.customer == 'object') {
                subscription.customer = subscription.customer.id;
            }

            // parse plan
            if (typeof subscription.plan == 'object') {
                subscription.plan = subscription.plan.id;
            }

            // parse start date
            if ($scope.startsNow) {
                subscription.start_date = moment().startOf('day').unix();
            } else {
                subscription.start_date = moment(subscription.start_date).startOf('day').unix();
            }

            // parse bill anniversary
            if ($scope.billOn === 'anniversary' || model.plan.interval === 'day') {
                subscription.snap_to_nth_day = null;
            } else if ($scope.billOn === 'nth_day') {
                subscription.snap_to_nth_day = parseInt(subscription.snap_to_nth_day);
                subscription.prorate = $scope.prorateFirstBill;
            }

            // parse contract terms
            subscription.cycles = SubscriptionCalculator.cyclesInDuration(
                { interval_count: $scope.contractIntervalCount, interval: $scope.contractInterval },
                model.plan,
            );

            subscription.contract_renewal_cycles = null;
            if (subscription.contract_renewal_mode === 'auto') {
                if (!$scope.sameRenewalLength) {
                    subscription.contract_renewal_cycles = SubscriptionCalculator.cyclesInDuration(
                        {
                            interval_count: $scope.contractRenewalIntervalCount,
                            interval: $scope.contractRenewalInterval,
                        },
                        model.plan,
                    );
                }
            }

            // parse rates
            angular.forEach(['discounts', 'taxes'], function (type) {
                angular.forEach(subscription[type], function (rate, index) {
                    subscription[type][index] = rate.id;
                });
            });

            // parse addons
            angular.forEach(subscription.addons, function (addon) {
                if (addon.catalog_item) {
                    addon.catalog_item = addon.catalog_item.id;
                }
                if (addon.plan) {
                    addon.plan = addon.plan.id;
                }
            });

            // parse payment method
            if (!$scope.useDefaultPaymentSource) {
                subscription.payment_source_type = $scope.paymentSource.object;
                subscription.payment_source_id = $scope.paymentSource.id;
            }

            // parse metadata
            MetadataCaster.marshalForInvoiced('subscription', subscription.metadata, function (metadata) {
                subscription.metadata = metadata;
                create(subscription);
            });
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

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
                                    customer: $scope.subscription.customer.id,
                                    plan: addon.plan.id,
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

        $scope.$watch(
            'subscription',
            function (subscription, old) {
                let customer = subscription.customer;
                if (customer) {
                    $scope.needsOnboarding = customer.autopay && !customer.payment_source;
                    loadPaymentSources(customer);
                }

                $scope.showStartDateWarning =
                    !$scope.startsNow && subscription.start_date <= moment().subtract(32, 'days');

                $scope.nthDayOptions = [];
                if (subscription.plan) {
                    if (!angular.equals(subscription.plan, old.plan)) {
                        // reset amount on non-custom plans because the field is hidden
                        if (subscription.plan.pricing_mode !== 'custom') {
                            subscription.amount = null;
                        }

                        $scope.billOn = 'anniversary';
                        $scope.contractIntervalCount = subscription.plan.interval_count;
                        $scope.contractInterval = subscription.plan.interval;
                        $scope.contractRenewalIntervalCount = subscription.plan.interval_count;
                        $scope.contractRenewalInterval = subscription.plan.interval;
                        SubscriptionCalculator.getPlanTotal(subscription, $scope, memoizedPreview);
                    } else if (subscription.quantity !== old.quantity) {
                        SubscriptionCalculator.getPlanTotal(subscription, $scope, memoizedPreview);
                    }

                    // determine renewal options
                    $scope.contractRenewalOptions = SubscriptionCalculator.contractRenewalOptions(subscription.plan);

                    // determine bill on nth day options
                    let interval = subscription.plan.interval;
                    if (interval !== 'day') {
                        let i, max;
                        if (interval === 'week') {
                            max = 7;
                        } else if (interval === 'month') {
                            max = 31;
                        } else if (interval === 'year') {
                            max = 365;
                        }

                        for (i = 1; i <= max; i++) {
                            $scope.nthDayOptions.push({
                                name: nthDayName(subscription.plan.interval, i),
                                value: i,
                            });
                        }
                    }
                }

                generatePreview(subscription);
            },
            true,
        );

        $scope.$watch('startsNow', function () {
            if ($scope.subscription.start_date) {
                $scope.showStartDateWarning =
                    !$scope.startsNow && $scope.subscription.start_date <= moment().subtract(32, 'days');
            }
            generatePreview($scope.subscription);
        });

        $scope.$watch('contractInterval', function () {
            generatePreview($scope.subscription);
        });

        $scope.$watch('contractIntervalCount', function () {
            generatePreview($scope.subscription);
        });

        $scope.$watch('billOn', function () {
            generatePreview($scope.subscription);
        });

        $scope.$watch('billIn', function () {
            generatePreview($scope.subscription);
        });

        $scope.$watch('prorateFirstBill', function () {
            generatePreview($scope.subscription);
        });

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        loadCustomFields();
        loadSettings();

        if (customer) {
            loadPaymentSources(customer);
        }

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

        function loadPaymentSources(customer) {
            if (loadedPaymentSources === customer.id || !customer.autopay) {
                return;
            }

            Customer.paymentSources(
                {
                    id: customer.id,
                },
                function (paymentSources) {
                    angular.forEach(paymentSources, function (source) {
                        source.name = PaymentDisplayHelper.format(source);
                    });

                    $scope.paymentSources = paymentSources;
                    loadedPaymentSources = customer.id;
                    $scope.paymentSource = customer.payment_source;
                    $scope.useDefaultPaymentSource = true;
                },
            );
        }

        function generatePreview(subscription) {
            let preview = false;
            if (subscription.customer && subscription.plan) {
                // Subscription Total
                SubscriptionCalculator.preview(subscription, $scope, memoizedPreview);

                // Calendar Billing
                let nthDay, nthDayMetric;
                let firstBillProrated = false;
                if ($scope.billOn === 'nth_day' && subscription.plan.interval !== 'day') {
                    nthDay = nthDayName(subscription.plan.interval, subscription.snap_to_nth_day);

                    if (subscription.plan.interval === 'week') {
                        nthDayMetric = 'E';
                    } else if (subscription.plan.interval === 'month') {
                        nthDay = 'the ' + nthDay;
                        nthDayMetric = 'D';
                    } else if (subscription.plan.interval === 'year') {
                        nthDay = 'the ' + nthDay;
                        nthDayMetric = 'DDD';
                    }

                    // check if first bill is prorated
                    if (subscription.snap_to_nth_day != moment().format(nthDayMetric)) {
                        if ($scope.startsNow || moment().isAfter(moment.unix(subscription.start_date))) {
                            firstBillProrated = $scope.prorateFirstBill;
                        } else {
                            firstBillProrated = true;
                        }
                    }
                }

                let start = moment().startOf('day');
                let interval = moment.duration(subscription.plan.interval_count, subscription.plan.interval);

                // Trial Period
                let trial = false;
                if (!$scope.startsNow && subscription.start_date) {
                    start = moment(subscription.start_date).startOf('day');

                    if (start.isAfter()) {
                        trial = {
                            start: moment().unix(),
                            end: start.unix() - 1,
                        };
                    }
                }

                // First Cycle
                let firstPeriodStart = moment(start);
                let firstPeriodEnd = moment(firstPeriodStart).add(interval);

                // factor in calendar billing
                if (nthDay) {
                    firstPeriodEnd = getSnappedDate(
                        nthDayMetric,
                        subscription.plan.interval,
                        firstPeriodStart,
                        subscription.snap_to_nth_day,
                    );
                }

                let renewal = getBillDate(firstPeriodStart.unix(), firstPeriodEnd.unix(), subscription);

                let firstCycle = {
                    start: firstPeriodStart.unix(),
                    end: firstPeriodEnd.unix() - 1,
                    renewal: renewal,
                    is_today: renewal === moment().startOf('day').unix(),
                };

                // Second Cycle
                let secondCycle = false;

                if (subscription.contract_renewal_mode === 'auto' || subscription.cycles > 1) {
                    let secondPeriodStart = firstPeriodEnd.unix();
                    let secondPeriodEnd = firstPeriodEnd.add(interval).unix() - 1;
                    renewal = getBillDate(secondPeriodStart, secondPeriodEnd, subscription);

                    secondCycle = {
                        start: secondPeriodStart,
                        end: secondPeriodEnd,
                        renewal: renewal,
                        is_today: renewal === moment().startOf('day').unix(),
                    };
                }

                // Final Step
                let cycles = 'until_canceled';
                if (subscription.contract_renewal_mode !== 'auto') {
                    cycles = SubscriptionCalculator.cyclesInDuration(
                        { interval_count: $scope.contractIntervalCount, interval: $scope.contractInterval },
                        subscription.plan,
                    );
                }

                preview = {
                    total: $scope.subscriptionTotal,
                    charged: subscription.customer.autopay,
                    payment_terms: subscription.customer.payment_terms,
                    trial: trial,
                    first_step: firstCycle,
                    second_step: secondCycle,
                    cycles: cycles,
                    nthDay: nthDay,
                    firstBillProrated: firstBillProrated,
                    bill_in: subscription.bill_in,
                };
            }

            $scope.preview = preview;
        }

        // start and end times in unix
        function getBillDate(start, end, subscription) {
            let billInAdvanceSeconds = subscription.bill_in_advance_days * 86400;

            if (subscription.bill_in === 'arrears') {
                return end - 1;
            } else if (start - billInAdvanceSeconds > moment().unix()) {
                return start - billInAdvanceSeconds;
            } else {
                // if the bill in advance offset causes the bill date to be in the past then
                // set the bill date instead to either the start date or now, whichever is earlier
                return Math.min(start, moment().startOf('day').unix());
            }
        }

        function create(params) {
            Subscription.create(
                params,
                function (subscription) {
                    $scope.saving = false;

                    $modalInstance.close(subscription);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
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

        let weekMapping = {
            1: 'Monday',
            2: 'Tuesday',
            3: 'Wednesday',
            4: 'Thursday',
            5: 'Friday',
            6: 'Saturday',
            7: 'Sunday',
        };

        function nthDayName(interval, i) {
            if (interval === 'week') {
                return weekMapping[i];
            }

            return ordinal_suffix_of(i);
        }

        function getSnappedDate(metric, interval, start, n) {
            // calculate next timestamp by adding days until we've
            // reached the target Nth day of week, month, or year.
            // ensures at least 1 iteration so the calculated timestamp
            // is always after the start date.
            let isFirst = true;
            let next = moment(start);
            let numDaysInMonth = 0;
            let isLastDayOfMonth = false;
            // Handles the edge case where the Nth day of the month
            // is greater than the # of days in certain months (> 28). The
            // solution is to use the last day of the month in that situation.
            while (
                isFirst ||
                (parseInt(next.format(metric)) != n &&
                    !(interval === 'month' && n > numDaysInMonth && isLastDayOfMonth))
            ) {
                isFirst = false;
                next = next.add(1, 'days');
                numDaysInMonth = parseInt(next.daysInMonth());
                isLastDayOfMonth = parseInt(next.format('D')) == numDaysInMonth;
            }

            // snap calculated timestamp to start of day
            return next.startOf('day');
        }

        /* Settings */

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
    }
})();
