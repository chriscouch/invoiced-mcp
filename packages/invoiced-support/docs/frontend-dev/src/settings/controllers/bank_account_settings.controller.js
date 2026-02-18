(function () {
    'use strict';

    angular.module('app.settings').controller('BankAccountSettingsController', BankAccountSettingsController);

    BankAccountSettingsController.$inject = [
        '$scope',
        '$modal',
        '$translate',
        'Core',
        'LeavePageWarning',
        'BankAccount',
    ];

    function BankAccountSettingsController($scope, $modal, $translate, Core, LeavePageWarning, BankAccount) {
        $scope.bankAccounts = [];
        $scope.deleting = [];

        Core.setTitle('Bank Accounts');
        load();

        $scope.new = function (item) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/bank_accounts/new.html',
                controller: 'NewAPBankAccountController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    item: function () {
                        return item;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('Bank account successfully saved', 'success');
                    LeavePageWarning.unblock();
                },
                function (result) {
                    LeavePageWarning.unblock();
                    // canceled
                    if (result === 'cancel') {
                        return;
                    }
                    Core.flashMessage(result.data, 'error');
                },
            );
        };

        $scope.delete = function (bankAccount, $index) {
            $scope.deleting[bankAccount.id] = true;
            BankAccount.delete(
                {
                    id: bankAccount.id,
                },
                {},
                function () {
                    $scope.deleting[bankAccount.id] = false;
                    Core.flashMessage('Bank account successfully deleted', 'success');
                    $scope.bankAccounts.splice($index, 1);
                },
                function (result) {
                    $scope.deleting[bankAccount.id] = false;
                    Core.flashMessage(result.data, 'error');
                },
            );
        };

        function load() {
            $scope.loading = true;
            BankAccount.findAll(
                {
                    expand: 'plaid',
                },
                function (all) {
                    $scope.loading = false;
                    $scope.bankAccounts = all;

                    angular.forEach(all, function (bankAccount) {
                        bankAccount._payment_methods = bankAccount.payment_methods
                            .map(function (paymentMethod) {
                                return $translate.instant('payment_method.' + paymentMethod);
                            })
                            .join(', ');
                    });
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
