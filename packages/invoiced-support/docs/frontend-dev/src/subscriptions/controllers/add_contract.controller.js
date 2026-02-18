/* globals moment */
(function () {
    'use strict';

    angular.module('app.subscriptions').controller('AddContractController', AddContractController);

    AddContractController.$inject = [
        '$scope',
        '$modalInstance',
        '$timeout',
        'Subscription',
        'selectedCompany',
        'SubscriptionCalculator',
        'subscription',
    ];

    function AddContractController(
        $scope,
        $modalInstance,
        $timeout,
        Subscription,
        selectedCompany,
        SubscriptionCalculator,
        subscription,
    ) {
        $scope.company = selectedCompany;

        $scope.id = subscription.id;
        $scope.hasContract = subscription.cycles > 0;
        $scope.renewalMode = subscription.contract_renewal_mode;
        $scope.renewsNext = subscription.renews_next;

        // determine default initial term / renewal term values
        $scope.contractInterval = subscription.plan.interval;
        $scope.contractIntervalCount = subscription.cycles;
        $scope.sameRenewalLength =
            !subscription.contract_renewal_cycles || subscription.cycles == subscription.contract_renewal_cycles;
        $scope.contractRenewalInterval = subscription.plan.interval;
        $scope.contractRenewalIntervalCount = subscription.plan.interval_count;

        // determine renewal options
        $scope.contractRenewalOptions = SubscriptionCalculator.contractRenewalOptions(subscription.plan);

        $scope.save = function (
            id,
            contractInterval,
            contractIntervalCount,
            renewalMode,
            contractRenewalInterval,
            contractRenewalIntervalCount,
        ) {
            $scope.saving = true;
            $scope.error = null;

            let cycles = SubscriptionCalculator.cyclesInDuration(
                { interval_count: contractIntervalCount, interval: contractInterval },
                subscription.plan,
            );

            let renewalCycles = null;
            if (renewalMode === 'auto') {
                if (!$scope.sameRenewalLength) {
                    renewalCycles = SubscriptionCalculator.cyclesInDuration(
                        { interval_count: contractRenewalIntervalCount, interval: contractRenewalInterval },
                        subscription.plan,
                    );
                }
            } else if (renewalMode === 'manual') {
                $scope.renewsNext = null;
            }

            let startDate = subscription.contract_period_start
                ? moment.unix(subscription.contract_period_start).toDate()
                : moment.unix(subscription.start_date).toDate();

            Subscription.edit(
                {
                    id: id,
                },
                {
                    contract_period_start: moment(startDate).unix(),
                    contract_period_end: null, // triggers a recalculation
                    cycles: cycles,
                    contract_renewal_mode: renewalMode,
                    contract_renewal_cycles: renewalCycles,
                    renews_next: $scope.renewsNext,
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

        $scope.removeContract = function (id) {
            $scope.saving = true;
            $scope.error = null;

            Subscription.edit(
                {
                    id: id,
                },
                {
                    contract_period_start: null,
                    contract_period_end: null,
                    cycles: null,
                    contract_renewal_cycles: null,
                    pending_renewal: false,
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
