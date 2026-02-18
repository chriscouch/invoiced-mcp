/* globals moment */
(function () {
    'use strict';

    angular.module('app.subscriptions').controller('ViewSubscriptionController', ViewSubscriptionController);

    ViewSubscriptionController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        '$controller',
        '$rootScope',
        '$modal',
        'Subscription',
        'Invoice',
        'LeavePageWarning',
        'Core',
        'Settings',
        'SubscriptionCalculator',
        'InvoiceCalculator',
        'BrowsingHistory',
        'Customer',
    ];

    function ViewSubscriptionController(
        $scope,
        $state,
        $stateParams,
        $controller,
        $rootScope,
        $modal,
        Subscription,
        Invoice,
        LeavePageWarning,
        Core,
        Settings,
        SubscriptionCalculator,
        InvoiceCalculator,
        BrowsingHistory,
        Customer,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Subscription;
        $scope.modelTitleSingular = 'Subscription';
        $scope.modelObjectType = 'subscription';

        //
        // Presets
        //

        let actionItems = [];
        $scope.tab = 'summary';
        $scope.subscriptionItems = [];

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'plan,customer,addons.catalog_item,addons.plan';
            findParams.include = 'num_invoices';
        };

        $scope.postFind = function (subscription) {
            $scope.subscription = subscription;

            let customer = subscription.customer;
            $rootScope.modelTitle = customer.name;
            Core.setTitle(customer.name + ' Subscription to ' + subscription.plan.name);

            calculate(subscription);

            loadUpcomingInvoice(subscription);
            loadSettings();
            BrowsingHistory.push({
                id: subscription.id,
                type: 'subscription',
                title: subscription.customer.name + ': ' + subscription.plan.name,
            });

            return subscription;
        };

        $scope.loadInvoices = function (id) {
            if ($scope.loaded.invoices || !id) {
                return;
            }

            Invoice.findAll(
                {
                    'filter[subscription]': id,
                    sort: 'date DESC',
                    per_page: 5,
                },
                function (invoices, headers) {
                    $scope.invoices = invoices;
                    $scope.moreInvoices = headers('X-Total-Count') > 5;
                    $scope.loaded.invoices = true;
                },
                function (result) {
                    $scope.loaded.invoices = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.editModal = function (subscription) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/edit-subscription.html',
                controller: 'EditSubscriptionController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    LeavePageWarning.unblock();
                    Core.flashMessage('Your subscription has been updated', 'success');
                    angular.extend(subscription, result);

                    // recalculate and reload invoices
                    calculate(subscription);
                    loadUpcomingInvoice(subscription);
                    $scope.loaded.invoices = false;
                    $scope.loadInvoices(subscription.id);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.addPaymentSource = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payments/add-payment-source.html',
                controller: 'AddPaymentSourceController',
                resolve: {
                    customer: function () {
                        return customer;
                    },
                    options: function () {
                        return {
                            makeDefault: true,
                        };
                    },
                },
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function (paymentSource) {
                    Core.flashMessage('The payment method was added', 'success');
                    $scope.subscription.customer.payment_source = paymentSource;
                    $scope.find($scope.subscription.id); // reload subscription
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.editStartDate = function (subscription) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/edit-next-period-start-date.html',
                controller: 'EditNextPeriodStartDateController',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
                size: 'sm',
            });

            modalInstance.result.then(
                function (updatedSubscription) {
                    LeavePageWarning.unblock();
                    Core.flashMessage('Your subscription has been updated', 'success');
                    angular.extend(subscription, updatedSubscription);

                    // recalculate and reload invoices
                    calculate(subscription);
                    loadUpcomingInvoice(subscription);
                    $scope.loaded.invoices = false;
                    $scope.loadInvoices(subscription.id);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.modifyContract = function (subscription) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/add-contract.html',
                controller: 'AddContractController',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
                size: 'sm',
            });

            modalInstance.result.then(
                function (updatedSubscription) {
                    LeavePageWarning.unblock();
                    Core.flashMessage('Your subscription has been updated', 'success');
                    angular.extend(subscription, updatedSubscription);
                    calculate(subscription);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.pause = function (subscription) {
            $scope.saving = true;
            Subscription.pause(
                {
                    id: subscription.id,
                },
                {},
                function (result) {
                    $scope.saving = false;
                    Core.flashMessage('Your subscription has been paused', 'success');

                    let plan = subscription.plan;
                    let customer = subscription.customer;
                    let addons = subscription.addons;
                    angular.extend(subscription, result);
                    subscription.plan = plan;
                    subscription.customer = customer;
                    subscription.addons = addons;

                    // recalculate and reload invoices
                    calculate(subscription);
                    loadUpcomingInvoice(subscription);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.resume = function (subscription) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/resume-subscription.html',
                controller: 'ResumeSubscriptionController',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
                size: 'sm',
            });

            modalInstance.result.then(
                function (updatedSubscription) {
                    LeavePageWarning.unblock();
                    Core.flashMessage('Your subscription has been resumed', 'success');
                    angular.extend(subscription, updatedSubscription);
                    calculate(subscription);
                    loadUpcomingInvoice(subscription);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.renewContract = function (subscription) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/renew-contract.html',
                controller: 'RenewContractController',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
                size: 'sm',
            });

            modalInstance.result.then(
                function (updatedSubscription) {
                    LeavePageWarning.unblock();
                    Core.flashMessage('Your contract has been renewed', 'success');
                    angular.extend(subscription, updatedSubscription);

                    // recalculate and reload invoices
                    calculate(subscription);
                    loadUpcomingInvoice(subscription);
                    $scope.loaded.invoices = false;
                    $scope.loadInvoices(subscription.id);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.urlModal = function (url) {
            $modal.open({
                templateUrl: 'components/views/url-modal.html',
                controller: 'URLModalController',
                resolve: {
                    url: function () {
                        return url;
                    },
                    customer: function () {
                        return null;
                    },
                },
            });
        };

        $scope.cancelModal = function (subscription) {
            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/cancel-subscription.html',
                controller: 'CancelSubscriptionController',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
            });

            modalInstance.result.then(
                function (updated) {
                    angular.extend(subscription, updated);
                    calculate(subscription);

                    // recalculate and reload invoices
                    calculate(subscription);
                    loadUpcomingInvoice(subscription);
                    if (subscription.status === 'canceled') {
                        Core.flashMessage('Your subscription has been canceled', 'success');
                    } else {
                        Core.flashMessage('Your subscription has been updated', 'success');
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.viewApproval = function (subscription) {
            $modal.open({
                templateUrl: 'subscriptions/views/approval-details.html',
                controller: 'SubscriptionApprovalDetailsController',
                resolve: {
                    approval: function () {
                        return subscription.approval;
                    },
                },
                size: 'sm',
            });
        };

        $scope.reactivate = function (subscription) {
            $scope.saving = true;
            Subscription.edit(
                {
                    id: subscription.id,
                },
                {
                    cancel_at_period_end: false,
                },
                function (result) {
                    $scope.saving = false;
                    Core.flashMessage('Your subscription has been reactivated', 'success');

                    let plan = subscription.plan;
                    let customer = subscription.customer;
                    let addons = subscription.addons;
                    angular.extend(subscription, result);
                    subscription.plan = plan;
                    subscription.customer = customer;
                    subscription.addons = addons;

                    // recalculate and reload invoices
                    calculate(subscription);
                    loadUpcomingInvoice(subscription);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.hasActionItem = function (k) {
            return actionItems.indexOf(k) !== -1;
        };

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Subscription');

        function calculate(subscription) {
            // calculate next period
            $scope.nextPeriod = false;
            let hasBillingCyclesLeft;

            if (subscription.bill_in === 'arrears') {
                hasBillingCyclesLeft =
                    !subscription.cancel_at_period_end || subscription.renews_next <= subscription.contract_period_end;
            } else {
                hasBillingCyclesLeft =
                    !subscription.cancel_at_period_end || subscription.renews_next < subscription.contract_period_end;
            }

            if (subscription.renews_next && hasBillingCyclesLeft) {
                let periodStart = moment.unix(subscription.period_end).endOf('day').add(1);
                let next = periodStart.clone();

                // Calendar Billing
                if (subscription.snap_to_nth_day) {
                    next = SubscriptionCalculator.getSnappedDate(subscription, next);
                } else {
                    next.add(subscription.plan.interval_count, subscription.plan.interval);
                }
                $scope.nextPeriod = {
                    renewal: subscription.renews_next,
                    start: periodStart.unix(),
                    end: next.startOf('day').subtract(1).unix(),
                };
            }

            if (subscription.cycles > 0) {
                subscription.contract_length = moment
                    .duration(subscription.plan.interval_count * subscription.cycles, subscription.plan.interval)
                    .humanize();
                subscription.remaining_cycles = subscription.cycles - subscription.num_invoices;
            } else {
                subscription.contract_length = null;
                subscription.remaining_cycles = null;
            }

            if (subscription.contract_renewal_cycles > 0) {
                subscription.contract_renewal_length = moment
                    .duration(
                        subscription.plan.interval_count * subscription.contract_renewal_cycles,
                        subscription.plan.interval,
                    )
                    .humanize();
            } else {
                subscription.contract_renewal_length = null;
            }

            // generate the action items
            computeActionItems(subscription);

            // Subscription items
            $scope.subscriptionItems = [];

            // Base plan
            $scope.subscriptionItems.push({
                plan: subscription.plan,
                item: null,
                quantity: subscription.quantity,
                description: subscription.description,
                amount: subscription.amount,
            });

            // Addons
            angular.forEach(subscription.addons, function (addon) {
                $scope.subscriptionItems.push({
                    plan: addon.plan,
                    item: addon.catalog_item,
                    quantity: addon.quantity,
                    description: addon.description,
                    amount: addon.amount,
                });
            });
        }

        function computeActionItems(subscription) {
            let customer = subscription.customer;

            let items = [];

            // needs onboarding
            if (
                customer.autopay &&
                !customer.payment_source &&
                subscription.status !== 'canceled' &&
                subscription.status !== 'finished'
            ) {
                items.push('needs_onboarding');
            }
            //for some reson isBetween is not working
            if (
                subscription.contract_renewal_mode === 'manual' &&
                //this one is need to assure that the current period is the last one
                subscription.renews_next === null &&
                moment().isAfter(moment.unix(subscription.period_start)) &&
                moment().isBefore(moment.unix(subscription.period_end))
            ) {
                items.push('is_renewable');
            }
            // pending renewal
            if (subscription.status === 'pending_renewal') {
                if (moment.unix(subscription.contract_period_end).isAfter(moment())) {
                    items.push('expiring_contract');
                } else {
                    items.push('expired_contract');
                }
            }

            // paused
            if (subscription.status === 'paused') {
                items.push('paused');
            }

            // cancellation
            if (subscription.cancel_at_period_end && subscription.status != 'canceled') {
                items.push('will_cancel');
            }

            actionItems = items;
        }

        function loadUpcomingInvoice(subscription) {
            Customer.upcomingInvoice(
                {
                    id: subscription.customer.id,
                    subscription: subscription.id,
                },
                function (invoice) {
                    $scope.upcomingInvoice = calculateInvoice(invoice);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function calculateInvoice(invoice) {
            $scope.upcomingInvoiceTotals = InvoiceCalculator.calculateSubtotalLines(invoice);

            angular.forEach(invoice.items, function (item) {
                item.hasMetadata = Object.keys(item.metadata).length > 0;
            });

            return invoice;
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
