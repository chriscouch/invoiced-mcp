(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('PaymentCalculator', PaymentCalculator);

    PaymentCalculator.$inject = ['Core', 'Money'];

    function PaymentCalculator(Core, Money) {
        return {
            calculateRemaining: calculateRemaining,
            validateAmount: validateAmount,
            validateCredits: validateCredits,
            validateAppliedTo: validateAppliedTo,
            validateAppliedSplits: validateAppliedSplits,
            calculateTree: calculateTree,
        };

        // Calculates the total applied and unapplied amount
        // remaining.
        function calculateRemaining(paymentAmount, appliedTo, availableCredits, currency) {
            paymentAmount = paymentAmount || 0;
            let totalApplied = 0;
            angular.forEach(appliedTo, function (line) {
                if (line.type !== 'credit_note' && line.amount > 0) {
                    totalApplied += Money.round(currency, parseFloat(line.amount));
                }
            });

            totalApplied = Money.round(currency, totalApplied);
            let remaining = Money.round(currency, paymentAmount - totalApplied);

            let totalCredits = 0;
            angular.forEach(availableCredits, function (line) {
                if (line.amount > 0) {
                    totalCredits += Money.round(currency, parseFloat(line.amount));
                }
            });
            remaining = Money.round(currency, remaining + totalCredits);

            return [totalApplied, remaining, totalCredits];
        }

        // Validates a payment amount.
        function validateAmount(amount, totalCredits) {
            if (amount === '' || amount === null) {
                return true;
            }

            if (amount > 0) {
                return true;
            }

            if (amount < 0) {
                return false;
            }

            // The payment amount can only be zero if there are
            // credits applied within the payment.
            return totalCredits > 0;
        }

        // Validates payment amount splits.
        function validateAppliedTo(applied) {
            let valid = true;
            angular.forEach(applied, function (line) {
                line.invalid = line.over = false;

                if (line.amount === '' || line.amount === null) {
                    return false;
                }

                // negative and zero amounts are invalid
                if (line.amount <= 0) {
                    line.invalid = true;
                    valid = false;
                    return false;
                }

                // amounts exceeding an invoice balance are invalid
                if (line.invoice && line.amount > line.invoice.balance) {
                    line.invalid = line.over = true;
                    valid = false;
                }

                // amounts exceeding document balance are invalid
                if (line.document && line.amount > line.document.balance) {
                    line.invalid = line.over = true;
                    valid = false;
                }
            });

            return valid;
        }

        // Validates payment available credits.
        function validateCredits(availableCredits) {
            let valid = true;
            angular.forEach(availableCredits, function (line) {
                line.invalid = line.over = false;

                if (line.amount === '' || line.amount === null) {
                    return;
                }

                // negative amounts are invalid
                if (line.amount < 0) {
                    line.invalid = true;
                    valid = false;
                    return;
                }

                // amounts exceeding the credit balance are invalid
                if (line.amount > line.balance) {
                    line.invalid = line.over = true;
                    valid = false;
                }
            });

            return valid;
        }

        // Validates payment amount splits.
        function validateAppliedSplits(applied) {
            let valid = true;
            angular.forEach(applied, function (line) {
                line.invalid = line.over = false;

                if (line.amount === '' || line.amount === null) {
                    return;
                }

                // negative amounts are invalid
                if (line.amount <= 0) {
                    line.invalid = true;
                    valid = false;
                    return;
                }

                // amounts exceeding an invoice balance are invalid
                if (line.invoice && line.amount > line.invoice.balance + line.originalAmount) {
                    line.invalid = line.over = true;
                    valid = false;
                }
            });

            return valid;
        }

        // Calculates the breakdown of a transaction tree.
        function calculateTree(tree) {
            let paid = 0;
            let appliedTo = [];
            let refunded = 0;
            let credited = 0;

            // walk the tree using breadth-first iteration
            // adding up any transactions in the process
            let searchQ = [tree];
            while (searchQ.length > 0) {
                // pop off next node
                let node = searchQ.splice(0, 1)[0];

                // add any child nodes to search queue
                if (node.children) {
                    searchQ = searchQ.concat(node.children);
                }

                // calculate how the node affects the total
                if (node.type == 'charge' || node.type == 'payment') {
                    paid += node.amount;
                } else if (node.type == 'refund') {
                    refunded += node.amount;
                } else if (node.type == 'adjustment') {
                    // NOTE credits are negative adjustments
                    credited -= node.amount;
                }

                appliedTo.push(node);
            }

            return {
                paid: paid,
                refunded: refunded,
                credited: credited,
                net: paid - refunded,
                appliedTo: appliedTo,
            };
        }
    }
})();
