/* globals moment */
(function () {
    'use strict';

    angular.module('app.settings').controller('PlaidDateRangeController', PlaidDateRangeController);

    PlaidDateRangeController.$inject = ['$scope', '$modalInstance', 'Core', 'CashApplicationBankService', 'plaidLink'];

    function PlaidDateRangeController($scope, $modalInstance, Core, CashApplicationBankService, plaidLink) {
        $scope.period = {
            start: moment().subtract(1, 'months').toDate(),
            end: moment().toDate(),
            period: ['months', 1],
        };

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
        };

        $scope.save = function (period) {
            $scope.saving = true;
            CashApplicationBankService.transactions(
                { id: plaidLink.id },
                {
                    start_date: moment(period.start).format('YYYY-MM-DD'),
                    end_date: moment(period.end).format('YYYY-MM-DD'),
                },
                function () {
                    $scope.saving = false;
                    Core.flashMessage('A transaction pull has been started for the requested date period.', 'success');
                    $modalInstance.close();
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
