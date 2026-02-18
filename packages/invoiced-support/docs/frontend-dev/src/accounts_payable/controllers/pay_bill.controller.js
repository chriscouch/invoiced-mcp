(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('PayBillController', PayBillController);

    PayBillController.$inject = [
        '$scope',
        '$state',
        '$modal',
        '$stateParams',
        '$translate',
        'Bill',
        'LeavePageWarning',
        'Core',
        'BankAccount',
        'Card',
        'Vendor',
        'PaymentDisplayHelper',
    ];

    function PayBillController(
        $scope,
        $state,
        $modal,
        $stateParams,
        $translate,
        Bill,
        LeavePageWarning,
        Core,
        BankAccount,
        Card,
        Vendor,
        PaymentDisplayHelper,
    ) {
        $scope.selectAccount = selectAccount;
        $scope.pay = pay;

        $scope.loading = 0;
        $scope.availablePaymentMethods = [];
        $scope.payFromOptions = [];
        $scope.parameters = { amount: null };
        $scope.creditCardConvenienceFee = null;

        $scope.newPaymentMethod = newPaymentMethod;

        Core.setTitle('Pay Bill');
        LeavePageWarning.watchForm($scope, 'modelForm');
        load();

        function load() {
            $scope.loading++;
            Bill.find(
                {
                    id: $stateParams.id,
                    expand: 'vendor',
                },
                function (bill) {
                    $scope.bill = bill;
                    $scope.loading--;

                    $scope.loading++;
                    Vendor.paymentMethods(
                        { id: bill.vendor.id },
                        paymentMethods => {
                            --$scope.loading;
                            angular.forEach(paymentMethods, function (paymentMethod) {
                                if (paymentMethod.type === 'credit_card' && paymentMethod.convenience_fee_percent) {
                                    $scope.creditCardConvenienceFee = paymentMethod.convenience_fee_percent / 100;
                                }
                            });
                        },
                        result => {
                            --$scope.loading;
                            Core.showMessage(result.data.message, 'error');
                        },
                    );
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );

            $scope.loading++;
            Bill.balance(
                {
                    id: $stateParams.id,
                },
                function (balance) {
                    $scope.balance = balance;
                    $scope.parameters.amount = balance.balance;
                    --$scope.loading;
                },
                function (result) {
                    --$scope.loading;
                    Core.showMessage(result.data.message, 'error');
                },
            );

            loadPaymentMethods();
        }

        function loadPaymentMethods() {
            $scope.payFromOptions = [];
            $scope.loading++;
            BankAccount.findAll(
                function (accounts) {
                    angular.forEach(accounts, function (account) {
                        account.type = 'bank_account';
                    });
                    $scope.payFromOptions = $scope.payFromOptions.concat(accounts);
                    --$scope.loading;
                },
                function (result) {
                    --$scope.loading;
                    Core.showMessage(result.data.message, 'error');
                },
            );

            $scope.loading++;
            Card.findAll(
                function (cards) {
                    angular.forEach(cards, function (card) {
                        card.type = 'card';
                        card.name = PaymentDisplayHelper.formatCard(card.brand, card.last4);
                        card.payment_methods = ['credit_card'];
                    });
                    $scope.payFromOptions = $scope.payFromOptions.concat(cards);
                    --$scope.loading;
                },
                function (result) {
                    --$scope.loading;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function selectAccount(account) {
            $scope.availablePaymentMethods = [];
            angular.forEach(account.payment_methods, function (methodId) {
                // ACH is not supported for one-off payments
                if (methodId === 'ach') {
                    return;
                }

                $scope.availablePaymentMethods.push({
                    id: methodId,
                    name: $translate.instant('payment_method.' + methodId),
                });
            });

            if ($scope.availablePaymentMethods.length > 0) {
                $scope.parameters.payment_method = $scope.availablePaymentMethods[0].id;
            } else {
                $scope.parameters.payment_method = null;
            }

            if (account.check_number) {
                $scope.parameters.check_number = account.check_number;
            }
        }

        function pay(parameters) {
            $scope.saving = true;
            $scope.error = null;

            const params = {
                vendor: parseInt($scope.bill.vendor.id),
                amount: parameters.amount,
                payment_method: parameters.payment_method,
                bills: [{ bill: $stateParams.id, amount: parameters.amount }],
            };

            if (parameters.pay_from.type === 'card') {
                params.card = parameters.pay_from.id;
            } else {
                params.bank_account = parameters.pay_from.id;
            }

            if (params.payment_method === 'print_check' || params.payment_method === 'echeck') {
                params.check_number = parameters.check_number;
            }

            Bill.pay(
                params,
                function (vendorPayment) {
                    $scope.saving = false;
                    LeavePageWarning.unblock();
                    $state.go('manage.vendor_payment.view.summary', { id: vendorPayment.id });
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data.message;
                },
            );
        }

        function newPaymentMethod() {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/payments/new-payment-method.html',
                controller: 'NewPayablesPaymentMethodController',
                windowClass: 'add-payment-method-modal',
                size: 'md',
            });
            modalInstance.result.then(
                function () {
                    loadPaymentMethods();
                },
                function () {
                    // canceled
                },
            );
        }
    }
})();
