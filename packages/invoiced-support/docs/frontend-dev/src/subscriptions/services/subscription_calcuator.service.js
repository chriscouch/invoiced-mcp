/* globals moment */
(function () {
    'use strict';

    angular.module('app.subscriptions').factory('SubscriptionCalculator', SubscriptionCalculator);

    SubscriptionCalculator.$inject = ['InvoiceCalculator'];

    function SubscriptionCalculator(InvoiceCalculator) {
        return {
            calculate: calculate,
            preview: preview,
            getPlanTotal: getPlanTotal,
            getAddonTotals: getAddonTotals,
            cyclesInDuration: cyclesInDuration,
            contractRenewalOptions: contractRenewalOptions,
            getSnappedDate: getSnappedDate,
        };

        function calculate(subscription, numberFormat) {
            if (!subscription.customer || !subscription.plan) {
                return 0;
            }

            // create an invoice with the base plan
            // NOTE plans are always taxable / discountable
            let invoice = {
                currency: subscription.plan.currency,
                items: [
                    {
                        unit_cost: subscription.plan.amount,
                        quantity: subscription.quantity,
                        discountable: true,
                        taxable: true,
                    },
                ],
                discounts: subscription.discounts,
                taxes: subscription.taxes,
            };

            // addons
            angular.forEach(subscription.addons, function (addon) {
                if (addon.catalog_item) {
                    invoice.items.push({
                        quantity: addon.quantity,
                        unit_cost: addon.catalog_item.unit_cost,
                        discountable: addon.catalog_item.discountable,
                        taxable: addon.catalog_item.taxable,
                    });
                } else if (addon.plan) {
                    invoice.items.push({
                        quantity: addon.quantity,
                        unit_cost: getPlanAmount(addon),
                    });
                }
            });

            // calculate it
            InvoiceCalculator.calculate(invoice, numberFormat);

            return invoice.total;
        }

        function preview(subscription, $scope, preview) {
            // format addons for preview
            let addonIds = [];
            angular.forEach(subscription.addons, function (addon) {
                if (addon.catalog_item) {
                    addonIds.push({
                        catalog_item: addon.catalog_item.id,
                        quantity: addon.quantity,
                    });
                } else if (addon.plan) {
                    addonIds.push({
                        plan: addon.plan.id,
                        amount: getPlanAmount(addon),
                        tiers: addon.plan.tiers,
                        quantity: addon.quantity,
                    });
                }
            });

            let result = preview(
                {
                    customer: subscription.customer.id,
                    plan: subscription.plan.id,
                    amount: getPlanAmount(subscription),
                    tiers: subscription.plan.tiers,
                    quantity: subscription.quantity,
                    addons: addonIds,
                    discounts: subscription.discounts.map(function (item) {
                        return item.id;
                    }),
                    taxes: subscription.taxes.map(function (item) {
                        return item.id;
                    }),
                },
                function (preview) {
                    $scope.subscriptionTotal = preview.recurring_total;
                },
                function (result) {
                    $scope.error = result.data;
                },
            );

            if (result) {
                $scope.subscriptionTotal = result.recurring_total;
            }
        }

        function getPlanTotal(subscription, $scope, preview) {
            let result = preview(
                {
                    plan: subscription.plan.id,
                    amount: getPlanAmount(subscription),
                    tiers: subscription.plan.tiers,
                    quantity: subscription.quantity,
                },
                function (preview) {
                    $scope.planTotal = preview.recurring_total;
                },
                function (result) {
                    $scope.error = result.data;
                },
            );

            if (result) {
                $scope.planTotal = result.recurring_total;
            }
        }

        function getAddonTotals($scope, preview) {
            angular.forEach($scope.subscription.addons, function (addon, index) {
                if (addon.plan) {
                    let result = preview(
                        {
                            plan: addon.plan.id,
                            amount: getPlanAmount(addon),
                            tiers: addon.plan.tiers,
                            quantity: addon.quantity,
                        },
                        function (preview) {
                            $scope.subscription.addons[index].total = preview.recurring_total;
                        },
                        function (result) {
                            $scope.error = result.data;
                        },
                    );

                    if (result) {
                        $scope.subscription.addons[index].total = result.recurring_total;
                    }
                }
            });
        }

        function cyclesInDuration(target, plan) {
            if (!target || !plan) {
                return 0;
            }

            let planDuration = moment.duration(plan.interval_count, plan.interval);
            let targetDuration = moment.duration(target.interval_count, target.interval);

            // figure out which multiple the plan duration is of the given duration
            let n = 1;
            while (targetDuration.subtract(planDuration).asDays() > 0) {
                n++;
            }

            return n;
        }

        function contractRenewalOptions(plan) {
            let options = [];

            if (plan.interval === 'day') {
                options.push({ name: 'Days', value: 'day' });
            }
            if (plan.interval === 'day' || plan.interval === 'week') {
                options.push({ name: 'Weeks', value: 'week' });
            }
            if (plan.interval === 'day' || plan.interval === 'week' || plan.interval === 'month') {
                options.push({ name: 'Months', value: 'month' });
            }
            options.push({ name: 'Years', value: 'year' });

            return options;
        }

        /**
         * Returns an amount based on the subscription's plan type
         */
        function getPlanAmount(subscription) {
            // check for custom plan
            if ('custom' === subscription.plan.pricing_mode) {
                return subscription.amount ? subscription.amount : 0;
            }

            return subscription.plan.amount;
        }

        function getSnappedDate(subscription, next) {
            let intervalCount = subscription.plan.interval_count;
            let date;
            switch (subscription.plan.interval) {
                case 'week':
                    date = next.isoWeekday();
                    if (date < subscription.snap_to_nth_day) {
                        --intervalCount;
                    }
                    next.add(intervalCount, 'week');
                    next.isoWeekday(subscription.snap_to_nth_day);
                    break;
                case 'month':
                    date = next.date();
                    if (date < Math.min(next.daysInMonth(), subscription.snap_to_nth_day)) {
                        --intervalCount;
                    }
                    next.startOf('month').add(intervalCount, 'month');
                    let toDate = Math.min(next.daysInMonth(), subscription.snap_to_nth_day);
                    next.date(toDate);
                    break;
                case 'year':
                    date = next.dayOfYear();
                    if (date < subscription.snap_to_nth_day) {
                        --intervalCount;
                    }
                    next.add(intervalCount, 'year');
                    next.dayOfYear(subscription.snap_to_nth_day);
                    break;
            }

            return next;
        }
    }
})();
