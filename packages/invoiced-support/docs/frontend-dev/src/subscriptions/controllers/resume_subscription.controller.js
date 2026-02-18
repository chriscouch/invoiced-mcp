/* globals moment */
(function () {
    'use strict';

    angular.module('app.subscriptions').controller('ResumeSubscriptionController', ResumeSubscriptionController);

    ResumeSubscriptionController.$inject = [
        '$scope',
        '$modalInstance',
        '$timeout',
        'Subscription',
        'selectedCompany',
        'Core',
        'subscription',
        'DatePickerService',
    ];

    function ResumeSubscriptionController(
        $scope,
        $modalInstance,
        $timeout,
        Subscription,
        selectedCompany,
        Core,
        subscription,
        DatePickerService,
    ) {
        $scope.company = selectedCompany;

        $scope.id = subscription.id;
        $scope.isTrialing = subscription.start_date > subscription.period_start;
        $scope.nextStartDate = moment.unix(subscription.period_end + 1).toDate();

        $scope.dateOptions = DatePickerService.getOptions({
            // cannot select a start date before the current one
            minDate: $scope.nextStartDate,
        });

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            $timeout(function () {
                /* CSS Z-indexing Overrides */
                $('#ui-datepicker-div').css('z-index', '9999');
            }, 100);
        };

        $scope.save = function (id, nextStartDate) {
            $scope.saving = true;
            $scope.error = null;

            Subscription.resume(
                {
                    id: subscription.id,
                },
                {
                    period_end: moment(nextStartDate).startOf('day').unix() - 1,
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
