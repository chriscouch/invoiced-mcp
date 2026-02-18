(function () {
    'use strict';

    angular.module('app.subscriptions').controller('RenewContractController', RenewContractController);

    RenewContractController.$inject = [
        '$scope',
        '$modalInstance',
        '$timeout',
        'Subscription',
        'SubscriptionCalculator',
        'selectedCompany',
        'Core',
        'subscription',
    ];

    function RenewContractController(
        $scope,
        $modalInstance,
        $timeout,
        Subscription,
        SubscriptionCalculator,
        selectedCompany,
        Core,
        subscription,
    ) {
        $scope.company = selectedCompany;

        // determine renewal options
        $scope.contractRenewalOptions = SubscriptionCalculator.contractRenewalOptions(subscription.plan);
        $scope.contractRenewalInterval = subscription.plan.interval;
        $scope.contractRenewalIntervalCount = subscription.plan.interval_count;

        $scope.save = function (renewalInterval, renewalIntervalCount) {
            $scope.saving = true;
            $scope.error = null;

            let cycles = SubscriptionCalculator.cyclesInDuration(
                { interval_count: renewalInterval, interval: renewalIntervalCount },
                subscription.plan,
            );

            Subscription.renewContract(
                {
                    id: subscription.id,
                },
                {
                    cycles: cycles,
                },
                function (updatedSubscription) {
                    $scope.saving = false;

                    // remove unexpanded properties
                    delete updatedSubscription.customer;
                    delete updatedSubscription.plan;
                    delete updatedSubscription.addons;

                    $modalInstance.close(updatedSubscription);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });
    }
})();
