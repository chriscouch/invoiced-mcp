(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('applyPayment', applyPayment);

    function applyPayment() {
        return {
            restrict: 'E',
            replace: true,
            templateUrl: 'accounts_receivable/views/payments/apply-payment.html',
            scope: {
                payment: '=',
                done: '&',
            },
            controller: [
                '$scope',
                'selectedCompany',
                'Invoice',
                'Money',
                'PaymentCalculator',
                'Payment',
                function ($scope, selectedCompany, Invoice, Money, PaymentCalculator, Payment) {
                    $scope.company = selectedCompany;

                    let payment = angular.copy($scope.payment);
                    $scope.payment = payment;

                    $scope.applied = [];
                    $scope.remaining = 0;
                    $scope.total = 0;
                    $scope.error = null;
                    $scope.previouslyApplied = payment.amount - payment.balance;
                    $scope.matches = [];
                    $scope.matchStatus = null;
                    $scope.matchCertainty = null;

                    findMatches();

                    $scope.addCredit = addCredit;
                    $scope.deleteLine = deleteLine;
                    $scope.nextMatch = nextMatch;

                    $scope.save = function (payment, applied, next) {
                        $scope.saving = true;
                        Payment.edit(
                            {
                                id: payment.id,
                            },
                            {
                                customer: parseInt(payment.customer.id),
                                applied_to: processApplied(payment, applied),
                            },
                            function () {
                                $scope.saving = false;
                                $scope.done({
                                    loadNext: next,
                                });
                            },
                            function (result) {
                                $scope.saving = false;
                                $scope.error = result.data;
                            },
                        );
                    };

                    $scope.$watch(
                        'payment',
                        function (current, old) {
                            if (current === old) {
                                return;
                            }

                            // check if selected customer has changed
                            if (!angular.equals(current.customer, old.customer)) {
                                loadInvoices();
                            }

                            // check if the balance has changed
                            if (current.balance !== old.balance && current.customer) {
                                loadInvoices();
                            }
                        },
                        true,
                    );

                    $scope.$watch(
                        'applied',
                        function (current, old) {
                            if (current === old) {
                                return;
                            }

                            changedAmount();
                        },
                        true,
                    );

                    // Loads all unpaid invoices for this customer.
                    function loadInvoices() {
                        $scope.loading = true;
                        Invoice.all(
                            {
                                'filter[customer]': $scope.payment.customer.id,
                                'filter[currency]': $scope.payment.currency,
                                'filter[paid]': false,
                                'filter[closed]': false,
                                'filter[draft]': false,
                                'filter[voided]': false,
                                sort: 'date ASC',
                            },
                            function (invoices) {
                                $scope.loading = false;

                                $scope.applied = [];
                                angular.forEach(invoices, function (invoice) {
                                    if (invoice.status != 'pending') {
                                        addInvoice(invoice);
                                    }
                                });

                                if (
                                    $scope.matches.length > 0 &&
                                    $scope.matches[0].customer.id == $scope.payment.customer.id
                                ) {
                                    applyMatches($scope.matches);
                                } else {
                                    autoApply();
                                }

                                $scope.matchStatus = determineMatchStatus($scope.matches);

                                // This must be called here when no invoices are found because
                                // in some cases the watcher might not fire because $scope.applied
                                // is unchanged.
                                if (invoices.length === 0) {
                                    changedAmount();
                                }
                            },
                            function (result) {
                                $scope.loading = false;
                                $scope.error = result.data;
                            },
                        );
                    }

                    function changedAmount() {
                        let totalShortPay = 0;
                        angular.forEach($scope.applied, function (line) {
                            if (line.invoice && line.shortPay) {
                                if (line.amount === 0) {
                                    line.shortPayAmount = Money.denormalizeFromZeroDecimal($scope.payment.currency, 0);
                                    line.shortPay = false;
                                } else {
                                    let shortPayAmount = Money.normalizeToZeroDecimal(
                                        $scope.payment.currency,
                                        line.invoice.balance - line.amount,
                                    );
                                    line.shortPayAmount = Money.denormalizeFromZeroDecimal(
                                        $scope.payment.currency,
                                        shortPayAmount,
                                    );
                                    totalShortPay += shortPayAmount;
                                }
                            }
                        });
                        $scope.totalShortPay = Money.denormalizeFromZeroDecimal($scope.payment.currency, totalShortPay);

                        calculateRemaining();

                        // validate payment amount and splits
                        $scope.invalidAmount = !PaymentCalculator.validateAmount($scope.payment.amount, 0);
                        PaymentCalculator.validateAppliedTo($scope.applied);

                        // do not show overpayment alert unlesss there
                        // is an unapplied amount remaining
                        $scope.showOverpaymentAlert = $scope.remaining > 0;
                    }

                    function calculateRemaining() {
                        let result = PaymentCalculator.calculateRemaining(
                            $scope.payment.balance,
                            $scope.applied,
                            [],
                            $scope.payment.currency,
                        );

                        $scope.total = result[0];
                        $scope.remaining = result[1];
                    }

                    function autoApply() {
                        // apply the payment amount in a waterfall fashion to oldest invoices first
                        let remaining = Money.normalizeToZeroDecimal($scope.payment.currency, $scope.payment.balance);

                        let i = 0;
                        let line;
                        while (remaining > 0 && i < $scope.applied.length) {
                            line = $scope.applied[i++];
                            if (!line.invoice) {
                                continue;
                            }

                            let balance = Money.normalizeToZeroDecimal(line.invoice.currency, line.invoice.balance);
                            let lineAmount = Math.min(remaining, balance);
                            line.amount = Money.denormalizeFromZeroDecimal(line.invoice.currency, lineAmount);
                            remaining -= lineAmount;
                        }
                    }

                    function applyMatches(matches) {
                        // apply the payment amount in a waterfall fashion to oldest invoices first
                        // TODO: the applied amount should be provided by the backend instead of approximated here
                        let remaining = Money.normalizeToZeroDecimal($scope.payment.currency, $scope.payment.balance);
                        angular.forEach(matches, function (match) {
                            if (!match.invoice) {
                                return;
                            }

                            for (let i in $scope.applied) {
                                let line = $scope.applied[i];
                                if (line.invoice && line.invoice.id === match.invoice.id) {
                                    let balance = Money.normalizeToZeroDecimal(
                                        match.invoice.currency,
                                        match.invoice.balance,
                                    );
                                    let lineAmount = Math.min(remaining, balance);
                                    line.amount = Money.denormalizeFromZeroDecimal(match.invoice.currency, lineAmount);
                                    remaining -= lineAmount;
                                    line.shortPay = match.short_pay && line.amount < line.invoice.balance;
                                }
                            }
                        });
                    }

                    function findMatches() {
                        $scope.loadingMatches = true;
                        Payment.matches(
                            {
                                id: payment.id,
                            },
                            function (matches) {
                                $scope.loadingMatches = false;
                                $scope.hasNextMatch = matches.pop().hasNextMatch;
                                $scope.matches = matches;

                                if ($scope.previouslyApplied !== 0) {
                                    $scope.customerPreselected = true;
                                } else if (matches.length > 0) {
                                    $scope.payment.customer = $scope.matches[0].customer;
                                    $scope.matchCertainty = $scope.matches[0].certainty;
                                }

                                determineMatchStatus($scope.matches);

                                if ($scope.payment.customer) {
                                    loadInvoices();
                                }
                            },
                            function (results) {
                                $scope.loadingMatches = false;
                                $scope.error = results.data;
                            },
                        );
                    }

                    function addInvoice(invoice) {
                        $scope.applied.push({
                            invoice: invoice,
                            amount: '',
                            shortPay: false,
                        });
                    }

                    function addCredit(amount) {
                        $scope.applied.push({
                            credit: true,
                            amount: amount,
                        });
                    }

                    function deleteLine(index) {
                        $scope.applied.splice(index, 1);
                    }

                    function processApplied(payment, applied) {
                        $scope.saving = true;
                        $scope.error = null;

                        // build the charge request
                        let splits = [];
                        angular.forEach(applied, function (line) {
                            if (line.invoice && line.amount) {
                                splits.push({
                                    type: 'invoice',
                                    invoice: line.invoice.id,
                                    amount: line.amount,
                                    short_pay: line.shortPay,
                                });
                            } else if (line.estimate && line.amount) {
                                splits.push({
                                    type: 'estimate',
                                    estimate: line.invoice.id,
                                    amount: line.amount,
                                });
                            } else if (line.credit) {
                                splits.push({
                                    type: 'credit',
                                    amount: line.amount,
                                });
                            }
                        });

                        return splits;
                    }

                    function determineMatchStatus(matches) {
                        Payment.find(
                            {
                                id: $scope.payment.id,
                            },
                            function (payment) {
                                $scope.payment.matched = payment.matched;
                                if (matches.length > 0) {
                                    // This handles the state where the payment did have a match
                                    // but the user changed the customer selection.
                                    if (matches[0].customer.id != $scope.payment.customer.id) {
                                        $scope.matchStatus = 'ignore';
                                        return;
                                    }

                                    if (matches[0].is_remittance_advice) {
                                        $scope.matchStatus = 'remittance_advice';
                                        return;
                                    }

                                    $scope.matchStatus = 'found';
                                    return;
                                }

                                if (payment.customer !== null) {
                                    $scope.matchStatus = null;
                                    return;
                                }

                                if (payment.matched === null) {
                                    $scope.matchStatus = 'pending';
                                    return;
                                }

                                $scope.matchStatus = 'none';
                            },
                            function (result) {
                                $scope.error = result.data;
                            },
                        );
                    }

                    function nextMatch() {
                        Payment.rejectMatch(
                            {
                                id: $scope.matches[0].group_id,
                            },
                            function () {
                                findMatches();
                            },
                            function (results) {
                                $scope.error = results.data;
                            },
                        );
                    }
                },
            ],
        };
    }
})();
