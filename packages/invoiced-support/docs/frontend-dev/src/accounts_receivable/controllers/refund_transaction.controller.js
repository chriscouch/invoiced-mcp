(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('RefundTransactionController', RefundTransactionController);

    RefundTransactionController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'selectedCompany',
        'Transaction',
        'PaymentCalculator',
        'payment',
        'maxAmount',
    ];

    function RefundTransactionController(
        $scope,
        $modal,
        $modalInstance,
        selectedCompany,
        Transaction,
        PaymentCalculator,
        payment,
        maxAmount,
    ) {
        $scope.company = selectedCompany;
        $scope.payment = payment;

        $scope.allowFullRefund = true;
        $scope.maxAmount = maxAmount;
        $scope.amount = $scope.maxAmount;
        $scope.type = 'full';
        $scope.subTransactions = [];

        $scope.refund = function (type, amount) {
            $scope.saving = true;
            $scope.error = null;

            // parse amount
            let queue = [];
            if (type === 'full') {
                // when there are sub-transactions the
                // refunds must be processed against each
                // split individually
                if ($scope.hasSubTransactions) {
                    queue = angular.copy($scope.subTransactions);
                    refundTransactions(queue, []);
                    return;
                } else {
                    amount = $scope.maxAmount;
                }
            } else if (type === 'partial') {
                if ($scope.hasSubTransactions) {
                    angular.forEach($scope.subTransactions, function (transaction) {
                        if (transaction.refund) {
                            queue.push(transaction);
                        }
                    });

                    if (queue.length === 0) {
                        $scope.saving = false;
                        $scope.error = {
                            message: '',
                        };
                        return;
                    }

                    refundTransactions(queue, []);
                    return;
                } else {
                    amount = Math.min($scope.maxAmount, amount);
                }
            } else {
                return;
            }

            Transaction.refund(
                {
                    id: $scope.payment.id,
                },
                {
                    amount: amount,
                },
                function (refund) {
                    $scope.saving = false;
                    $modalInstance.close([refund]);
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

        if (typeof payment.children !== 'undefined') {
            calculate(payment);
        } else {
            load(payment);
        }

        function load(payment) {
            $scope.loading = true;
            Transaction.find(
                {
                    id: payment.id,
                    include: 'children',
                },
                function (_payment) {
                    $scope.loading = false;
                    calculate(_payment);
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data;
                },
            );
        }

        function calculate(payment) {
            let result = PaymentCalculator.calculateTree(payment);

            if (result.refunded > 0) {
                $scope.allowFullRefund = false;
                $scope.type = 'partial';
            }

            if (result.net <= 0) {
                $scope.alreadyRefunded = true;
            }

            $scope.amount = result.net;
            $scope.maxAmount = result.net;
            $scope.subTransactions = [];

            // first determine which splits have been refunded
            let refundedSplits = [];
            angular.forEach(result.appliedTo, function (transaction) {
                if (transaction.type === 'refund') {
                    refundedSplits.push(transaction.parent_transaction);
                }
            });

            // then compile the remaining splits
            let numPayments = 0;
            angular.forEach(result.appliedTo, function (transaction) {
                if (transaction.type === 'payment' || transaction.type === 'charge') {
                    numPayments++;
                    if (refundedSplits.indexOf(transaction.id) !== -1) {
                        return;
                    }

                    if (transaction.id == payment.id) {
                        // this is the parent transaction which has
                        // its split amount calculated differently
                        transaction.maxAmount = transaction.amount;
                        $scope.subTransactions.push(transaction);
                    } else {
                        // otherwise we have a split that we must check if
                        // it has been refunded
                        let splitResult = PaymentCalculator.calculateTree(transaction);

                        // check if this split has not been fully refunded
                        if (splitResult.net > 0) {
                            transaction.maxAmount = splitResult.net;
                            $scope.subTransactions.push(transaction);
                        }
                    }
                }
            });

            $scope.hasSubTransactions = numPayments > 1;
        }

        function refundTransactions(queue, refunds) {
            if (queue.length === 0) {
                $scope.saving = false;
                $modalInstance.close(refunds);
                return;
            }

            Transaction.refund(
                {
                    id: queue[0].id,
                },
                {
                    amount: queue[0].maxAmount,
                },
                function (transaction) {
                    transaction.customer = queue[0].customer;
                    transaction.invoice = queue[0].invoice;
                    transaction.credit_note = queue[0].credit_note;
                    refunds.push(transaction);
                    queue.splice(0, 1);
                    refundTransactions(queue, refunds);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
