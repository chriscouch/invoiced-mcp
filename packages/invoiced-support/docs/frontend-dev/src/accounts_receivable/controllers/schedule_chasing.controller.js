/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('ScheduleChasingController', ScheduleChasingController);

    ScheduleChasingController.$inject = ['$scope', '$modalInstance', 'Core', 'Invoice', 'invoice', 'DatePickerService'];

    function ScheduleChasingController($scope, $modalInstance, Core, Invoice, invoice, DatePickerService) {
        $scope.invoice = invoice;
        $scope.next = invoice.next_chase_on;
        if (typeof $scope.next === 'number' && $scope.next > 0) {
            $scope.next = moment.unix($scope.next).toDate();
        } else {
            $scope.next = moment().add(1, 'days').toDate();
        }

        $scope.dateOptions = DatePickerService.getOptions({
            // cannot select a date in the past or today
            minDate: 1,
        });

        $scope.save = function (next, invoice) {
            $scope.saving = true;

            Invoice.edit(
                {
                    id: invoice.id,
                },
                {
                    next_chase_on: moment(next).unix(),
                    next_chase_step: 'email',
                },
                function (_invoice) {
                    $scope.saving = false;
                    invoice.next_chase_on = _invoice.next_chase_on;
                    $modalInstance.close(invoice);
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
