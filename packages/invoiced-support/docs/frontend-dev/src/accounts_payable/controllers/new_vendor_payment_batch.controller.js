(function () {
    'use strict';

    angular
        .module('app.accounts_payable')
        .controller('NewVendorPaymentBatchController', NewVendorPaymentBatchController);

    NewVendorPaymentBatchController.$inject = [
        '$scope',
        '$modal',
        '$state',
        '$q',
        '$translate',
        'Bill',
        'VendorPaymentBatch',
        'LeavePageWarning',
        'Core',
        'BankAccount',
        'Card',
        'PaymentDisplayHelper',
        'selectedCompany',
    ];

    function NewVendorPaymentBatchController(
        $scope,
        $modal,
        $state,
        $q,
        $translate,
        Bill,
        VendorPaymentBatch,
        LeavePageWarning,
        Core,
        BankAccount,
        Card,
        PaymentDisplayHelper,
        selectedCompany,
    ) {
        $scope.loading = 0;
        $scope.availablePaymentMethods = [];
        $scope.bills = [];
        $scope.payFromOptions = [];
        $scope.checked = false;
        $scope.total = 0;
        $scope.parameters = {
            name: 'Payment Batch',
            number: null,
            pay_from: null,
            payment_method: null,
            initial_check_number: null,
            check_layout: null,
        };
        $scope.paymentTotal = 0;
        $scope.currency = 'usd';

        Core.setTitle('Send Payment');
        LeavePageWarning.watchForm($scope, 'paymentForm');

        $scope.selectAll = function (checked) {
            angular.forEach($scope.bills, function (bill) {
                bill.checked = checked;
            });
        };

        $scope.formValid = function () {
            const bills = $scope.bills.filter(function (bill) {
                return bill.checked;
            });

            if (!bills.length) {
                return false;
            }

            for (let i in bills) {
                if (bills[i].amount <= 0 || bills[i].amount > bills[i].balance) {
                    return false;
                }
            }

            return true;
        };

        $scope.newPaymentMethod = function () {
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
        };

        $scope.save = function (parameters, bills) {
            $scope.saving = true;

            const ids = bills
                .filter(function (bill) {
                    return bill.checked;
                })
                .map(function (bill) {
                    return {
                        id: bill.id,
                        amount: bill.amount,
                    };
                });

            const params = {
                name: parameters.name,
                number: parameters.number,
                bills: ids,
                payment_method: parameters.payment_method,
            };

            if (parameters.pay_from.type === 'card') {
                params.card = parameters.pay_from.id;
            } else {
                params.bank_account = parameters.pay_from.id;
            }

            if (params.payment_method === 'print_check') {
                params.initial_check_number = parameters.initial_check_number;
                params.check_layout = parameters.check_layout;
            } else if (params.payment_method === 'echeck') {
                params.initial_check_number = parameters.initial_check_number;
            }

            VendorPaymentBatch.create(
                params,
                function (batchPayment) {
                    $scope.saving = false;
                    LeavePageWarning.unblock();
                    $state.go('manage.vendor_payment_batches.summary', { id: batchPayment.id });
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.selectAccount = function (account) {
            $scope.availablePaymentMethods = [];
            angular.forEach(account.payment_methods, function (methodId) {
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

            $scope.parameters.check_layout = account.check_layout;
            if (account.check_number) {
                $scope.parameters.initial_check_number = account.check_number;
            }
        };

        $scope.$watch(
            'bills',
            function () {
                $scope.paymentTotal = 0;
                $scope.currency = null;
                angular.forEach($scope.bills, function (bill) {
                    if (bill.checked) {
                        $scope.currency = $scope.currency || bill.currency;
                        $scope.paymentTotal += bill.amount;
                    }
                });
                $scope.currency = $scope.currency || selectedCompany.currency;
            },
            true,
        );

        load();

        function load() {
            $scope.loading++;
            VendorPaymentBatch.billsToPay(
                function (vendors) {
                    $scope.bills = [];
                    angular.forEach(vendors, function (vendorRow) {
                        let creditCardConvenienceFee = null;
                        angular.forEach(vendorRow.payment_methods, function (paymentMethod) {
                            if (paymentMethod.type === 'credit_card' && paymentMethod.convenience_fee_percent) {
                                creditCardConvenienceFee = paymentMethod.convenience_fee_percent / 100;
                            }
                        });

                        angular.forEach(vendorRow.bills, function (bill) {
                            bill.vendor = vendorRow.vendor;
                            bill.checked = false;
                            bill.amount = bill.balance;
                            bill.creditCardConvenienceFee = creditCardConvenienceFee;
                            $scope.bills.push(bill);
                        });
                    });

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
    }
})();
