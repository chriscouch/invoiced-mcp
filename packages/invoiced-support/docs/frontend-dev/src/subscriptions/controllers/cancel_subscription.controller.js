(function () {
    'use strict';

    angular.module('app.subscriptions').controller('CancelSubscriptionController', CancelSubscriptionController);

    CancelSubscriptionController.$inject = [
        '$scope',
        '$modalInstance',
        'SubscriptionCalculator',
        'Subscription',
        'selectedCompany',
        'subscription',
    ];

    function CancelSubscriptionController(
        $scope,
        $modalInstance,
        SubscriptionCalculator,
        Subscription,
        selectedCompany,
        subscription,
    ) {
        $scope.subscription = subscription;
        $scope.plan = subscription.plan;
        $scope.company = selectedCompany;
        $scope.when = 'now';

        $scope.customerName =
            typeof subscription.customer === 'object' ? subscription.customer.name : subscription.customerName;
        $scope.amount = SubscriptionCalculator.calculate(subscription, selectedCompany.moneyFormat);

        $scope.cancel = function (subscription, when) {
            if (when === 'now') {
                cancelNow(subscription);
            } else if (when === 'period_end') {
                cancelAtPeriodEnd(subscription);
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function cancelNow(subscription) {
            $scope.canceling = true;
            $scope.error = null;

            Subscription.cancel(
                {
                    id: subscription.id,
                },
                function (canceledSubscription) {
                    $scope.canceling = false;

                    let customer = subscription.customer;
                    let plan = subscription.plan;
                    angular.extend(subscription, canceledSubscription);
                    subscription.customer = customer;
                    subscription.plan = plan;

                    $modalInstance.close(subscription);
                },
                function (result) {
                    $scope.canceling = false;
                    $scope.error = result.data;
                },
            );
        }

        function cancelAtPeriodEnd(subscription) {
            $scope.canceling = true;
            $scope.error = null;

            Subscription.edit(
                {
                    id: subscription.id,
                },
                {
                    cancel_at_period_end: true,
                },
                function () {
                    $scope.canceling = false;

                    subscription.cancel_at_period_end = true;

                    $modalInstance.close(subscription);
                },
                function (result) {
                    $scope.canceling = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
