/* globals moment */
(function () {
    'use strict';

    angular
        .module('app.subscriptions')
        .controller('EditNextPeriodStartDateController', EditNextPeriodStartDateController);

    EditNextPeriodStartDateController.$inject = [
        '$scope',
        '$modalInstance',
        '$timeout',
        'Subscription',
        'selectedCompany',
        'Core',
        'subscription',
        'DatePickerService',
    ];

    function EditNextPeriodStartDateController(
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
        $scope.isTrialing = subscription.status === 'not_started';
        $scope.nextStartDate = moment.unix(subscription.period_end + 1).toDate();

        $scope.dateOptions = DatePickerService.getOptions({
            // cannot select a start date before the current one
            minDate: $scope.isTrialing ? 0 : $scope.nextStartDate,
        });

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            $timeout(function () {
                /* CSS Z-indexing Overrides */
                $('#ui-datepicker-div').css('z-index', '9999');
            }, 100);
        };

        $scope.updateBillDate = function (nextStartDate) {
            let offset = subscription.bill_in_advance_days * 86400;
            nextStartDate = moment(nextStartDate).unix();
            $scope.nextBillDate = moment.unix(nextStartDate - offset).format('MMM Do, YYYY');
        };
        $scope.updateBillDate($scope.nextStartDate);

        $scope.save = function (id, nextStartDate) {
            $scope.saving = true;
            $scope.error = null;

            Subscription.edit(
                {
                    id: id,
                },
                {
                    period_end: moment(nextStartDate).startOf('day').unix() - 1,
                    snap_to_nth_day: null,
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
