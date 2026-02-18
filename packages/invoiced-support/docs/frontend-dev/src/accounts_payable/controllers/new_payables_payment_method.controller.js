(function () {
    'use strict';

    angular
        .module('app.accounts_payable')
        .controller('NewPayablesPaymentMethodController', NewPayablesPaymentMethodController);

    NewPayablesPaymentMethodController.$inject = ['$scope', '$modalInstance', '$modal', 'Core', 'LeavePageWarning'];

    function NewPayablesPaymentMethodController($scope, $modalInstance, $modal, Core, LeavePageWarning) {
        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.addBankAccount = function () {
            LeavePageWarning.block();
            $('.add-payment-method-modal').hide();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/bank_accounts/new.html',
                controller: 'NewAPBankAccountController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    item: function () {
                        return null;
                    },
                },
            });

            modalInstance.result.then(
                function (bankAccount) {
                    Core.flashMessage('Bank account successfully saved', 'success');
                    LeavePageWarning.unblock();
                    $modalInstance.close(bankAccount);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                    $('.add-payment-method-modal').show();
                },
            );
        };

        $scope.addCard = function () {
            LeavePageWarning.block();
            $('.add-payment-method-modal').hide();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/cards/new.html',
                controller: 'NewCardController',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function (card) {
                    Core.flashMessage('Card successfully saved', 'success');
                    LeavePageWarning.unblock();
                    $modalInstance.close(card);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                    $('.add-payment-method-modal').show();
                },
            );
        };
    }
})();
