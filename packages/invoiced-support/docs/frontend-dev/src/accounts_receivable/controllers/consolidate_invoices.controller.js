/* globals moment */
(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('ConsolidateInvoicesController', ConsolidateInvoicesController);

    ConsolidateInvoicesController.$inject = [
        '$scope',
        '$modalInstance',
        '$timeout',
        'Customer',
        'customer',
        'DatePickerService',
    ];

    function ConsolidateInvoicesController($scope, $modalInstance, $timeout, Customer, customer, DatePickerService) {
        $scope.customer = customer;

        $scope.dateOptions = DatePickerService.getOptions({
            maxDate: '0',
        });

        $scope.cutoffDate = new Date();

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            // this is needed to ensure the datepicker
            // can be opened again
            $timeout(function () {
                $scope[name] = false;
            });
        };

        $scope.submit = function (cutoffDate) {
            $scope.saving = true;
            $scope.error = null;

            Customer.consolidateInvoices(
                {
                    id: customer.id,
                },
                {
                    cutoff_date: moment(cutoffDate).endOf('day').unix(),
                },
                function (consolidatedInvoice) {
                    $scope.saving = false;

                    $modalInstance.close(consolidatedInvoice);
                },
                function (result) {
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
