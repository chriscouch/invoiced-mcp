(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('VerifyBankAccountController', VerifyBankAccountController);

    VerifyBankAccountController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'Customer',
        'PaymentMethod',
        'Core',
        'PaymentTokens',
        'PaymentDisplayHelper',
        'selectedCompany',
        'customer',
        'bankAccount',
    ];

    function VerifyBankAccountController(
        $scope,
        $modal,
        $modalInstance,
        Customer,
        PaymentMethod,
        Core,
        PaymentTokens,
        PaymentDisplayHelper,
        selectedCompany,
        customer,
        bankAccount,
    ) {
        bankAccount.description = PaymentDisplayHelper.formatBankAccount(bankAccount.bank_name, bankAccount.last4);
        $scope.bankAccount = bankAccount;
        $scope.amount1 = '';
        $scope.amount2 = '';

        $scope.verify = function (amount1, amount2) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                amount1: amount1,
                amount2: amount2,
            };

            Customer.verifyBankAccount(
                {
                    id: bankAccount.id,
                    customer: customer.id,
                },
                params,
                function (bankAccount) {
                    $scope.saving = false;
                    $modalInstance.close(bankAccount);
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
    }
})();
