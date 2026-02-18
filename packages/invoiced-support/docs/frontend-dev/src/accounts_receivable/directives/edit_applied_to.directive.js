(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('editAppliedTo', editAppliedTo);

    function editAppliedTo() {
        return {
            restrict: 'E',
            replace: true,
            templateUrl: 'accounts_receivable/views/payments/edit-applied-to.html',
            scope: {
                payment: '=',
                done: '&',
            },
            controller: [
                '$scope',
                'Money',
                'PaymentCalculator',
                'Payment',
                function ($scope, Money, PaymentCalculator, Payment) {
                    $scope.payment = angular.copy($scope.payment);
                    $scope.appliedTo = angular.copy($scope.payment.applied_to);

                    angular.forEach($scope.appliedTo, function (line) {
                        line.originalAmount = line.amount;
                        line.amount = line.originalAmount;
                    });

                    changedAmount();

                    $scope.deleteLine = deleteLine;

                    $scope.save = function (payment, applied) {
                        $scope.saving = true;
                        let params = {
                            applied_to: processApplied(payment, applied),
                        };

                        Payment.edit(
                            {
                                id: payment.id,
                            },
                            params,
                            function () {
                                $scope.saving = false;
                                $scope.done({
                                    loadNext: false,
                                });
                            },
                            function (result) {
                                $scope.saving = false;
                                $scope.error = result.data;
                            },
                        );
                    };

                    $scope.$watch(
                        'appliedTo',
                        function (current, old) {
                            if (current === old) {
                                return;
                            }

                            changedAmount();
                        },
                        true,
                    );

                    function changedAmount() {
                        calculateRemaining();

                        // validate payment amount and splits
                        $scope.invalidAmount = !PaymentCalculator.validateAmount($scope.payment.amount, 0);
                        PaymentCalculator.validateAppliedSplits($scope.appliedTo);

                        // do not show overpayment alert unlesss there
                        // is an unapplied amount remaining
                        $scope.showOverpaymentAlert = $scope.remaining > 0;
                    }

                    function calculateRemaining() {
                        let result = PaymentCalculator.calculateRemaining(
                            $scope.payment.amount,
                            $scope.appliedTo,
                            [],
                            $scope.payment.currency,
                        );

                        $scope.total = result[0];
                        $scope.remaining = result[1];
                    }

                    function deleteLine(index) {
                        $scope.appliedTo.splice(index, 1);
                    }

                    function processApplied(payment, applied) {
                        let splits = [];
                        angular.forEach(applied, function (line) {
                            if (line.amount <= 0) {
                                return;
                            }

                            let element = {
                                type: line.type,
                                amount: line.amount,
                            };
                            if (typeof line.id !== 'undefined') {
                                element.id = line.id;
                            }

                            if (line.type === 'invoice') {
                                element.invoice = line.invoice;
                            } else if (line.type === 'estimate') {
                                element.estimate = line.estimate;
                            } else if (line.type === 'credit_note') {
                                element.credit_note = line.credit_note;
                                if (line.document_type) {
                                    element.document_type = line.document_type;
                                    element[line.document_type] = line[line.document_type];
                                }
                            }

                            splits.push(element);
                        });

                        return splits;
                    }
                },
            ],
        };
    }
})();
