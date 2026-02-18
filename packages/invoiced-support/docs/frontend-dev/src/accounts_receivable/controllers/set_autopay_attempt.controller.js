/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('SetAutoPayAttemptController', SetAutoPayAttemptController);

    SetAutoPayAttemptController.$inject = [
        '$scope',
        '$modalInstance',
        'Invoice',
        'Core',
        'invoice',
        'DatePickerService',
    ];

    function SetAutoPayAttemptController($scope, $modalInstance, Invoice, Core, invoice, DatePickerService) {
        if (invoice.next_payment_attempt > 0) {
            $scope.nextAttempt = moment.unix(invoice.next_payment_attempt).toDate();
        } else {
            $scope.nextAttempt = new Date();
        }

        $scope.dateOptions = DatePickerService.getOptions({
            // cannot select an expiration date in the past or today
            minDate: 0,
        });

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
        };

        $scope.save = function (nextAttempt) {
            $scope.saving = true;
            nextAttempt = moment(nextAttempt).startOf('day').unix();

            Invoice.edit(
                {
                    id: invoice.id,
                },
                {
                    next_payment_attempt: nextAttempt,
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close(nextAttempt);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
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
