/* globals moment */
(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('AddCreditBalanceAdjustmentController', AddCreditBalanceAdjustmentController);

    AddCreditBalanceAdjustmentController.$inject = [
        '$scope',
        '$modalInstance',
        'CreditBalanceAdjustment',
        'Core',
        'selectedCompany',
        'currency',
        'amount',
        'customer',
        'DatePickerService',
    ];

    function AddCreditBalanceAdjustmentController(
        $scope,
        $modalInstance,
        CreditBalanceAdjustment,
        Core,
        selectedCompany,
        currency,
        amount,
        customer,
        DatePickerService,
    ) {
        $scope.adjustment = {
            customer: customer,
            date: new Date(),
            currency: currency,
            amount: amount
                ? parseFloat(
                      parseFloat(Core.parseFormattedNumber(amount)).formatMoney(
                          2,
                          selectedCompany.decimal_separator,
                          '',
                      ),
                  )
                : '',
            notes: '',
        };

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
        };

        $scope.save = function (adjustment) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                currency: adjustment.currency,
                customer: adjustment.customer.id,
                // The time of day on credit balance adjustments should be 6am local time zone
                date: moment(adjustment.date).hour(6).minute(0).second(0).unix(),
                notes: adjustment.notes,
            };

            // parse amount
            params.amount = parseFloat(
                parseFloat(Core.parseFormattedNumber(adjustment.amount)).formatMoney(
                    2,
                    selectedCompany.decimal_separator,
                    '',
                ),
            );

            CreditBalanceAdjustment.create(
                params,
                function () {
                    $scope.saving = false;
                    $modalInstance.close();
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
