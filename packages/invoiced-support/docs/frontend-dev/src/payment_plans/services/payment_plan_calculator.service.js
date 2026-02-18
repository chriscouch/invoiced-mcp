/* globals moment */
(function () {
    'use strict';

    angular.module('app.payment_plans').factory('PaymentPlanCalculator', PaymentPlanCalculator);

    PaymentPlanCalculator.$inject = ['Money'];

    function PaymentPlanCalculator(Money) {
        return {
            build: build,
            verify: verify,
        };

        /*
        Possible payment plan scenarios (that we handle):
        1. Fixed installment amount and fixed spacing
        2. Fixed installment amount and fixed stop date
        3. Fixed # of installments and fixed spacing
        4. Fixed # of installments and fixed stop date
        5. Fixed spacing and fixed end date

        Whenever one of these combinations are presented we need
        to be able to compute a valid installment schedule from the
        given constraints.
        */

        function build(constraints) {
            let schedule = [];
            let values = angular.copy(constraints);

            // Check for expected values
            if (!values.currency) {
                throw 'Missing currency.';
            }

            if (!(values.start_date instanceof Date)) {
                throw 'Missing start date.';
            }

            // 1. End Date
            values.end_date = calcEndDate(values);

            // 2. # Installments
            values.num_installments = calcNumInstallments(values);
            applyNumInstallments(schedule, values);

            // 3. Start Date
            applyStartDate(schedule, values);

            // 4. Installment Spacing
            values.installment_spacing = calcInstallmentSpacing(values);
            applyInstallmentSpacing(schedule, values);

            // 5. Installment Amount
            values.installment_amount = calcInstallmentAmount(values);
            applyInstallmentAmount(schedule, values);

            return schedule;
        }

        function calcEndDate(values) {
            // use given value
            if (typeof values.end_date !== 'undefined') {
                return values.end_date;
            }

            // calculate the end date from start date, spacing, and # installments
            if (typeof values.installment_spacing !== 'undefined' && typeof values.num_installments !== 'undefined') {
                let end = moment(values.start_date);
                for (let i = 1; i < values.num_installments; i++) {
                    end.add(values.installment_spacing);
                }
                return end.toDate();
            }

            // if we cannot calculate the end date, then that's ok
            return undefined;
        }

        function calcNumInstallments(values) {
            let n = false;

            // use given constraint
            if (values.num_installments) {
                n = values.num_installments;
            }

            // calculate from start date, end date, and spacing
            if (!n && typeof values.end_date !== 'undefined' && typeof values.installment_spacing !== 'undefined') {
                let start = moment(values.start_date);
                let end = moment(values.end_date);
                // compute diff in ms
                let diff = end.diff(start);
                n = Math.ceil(diff / values.installment_spacing.asMilliseconds()) + 1;
            }

            // calculate from balance and installment amount
            if (!n && typeof values.total !== 'undefined' && typeof values.installment_amount !== 'undefined') {
                n = Math.ceil(values.total / values.installment_amount);
            }

            // we have incomplete information
            if (n === false) {
                throw 'Incomplete information. Please add more constraints.';
            }

            // verify the computed amount
            n = parseInt(n);
            if (isNaN(n) || n < 1) {
                throw 'Does not produce at least 1 installment.';
            }

            return n;
        }

        function applyNumInstallments(schedule, values) {
            // create N blank installments
            for (let i = 0; i < values.num_installments; i++) {
                schedule.push({});
            }
        }

        function applyStartDate(schedule, values) {
            // set the date of the first installment
            schedule[0].date = moment(values.start_date).startOf('day').toDate();
        }

        function calcInstallmentSpacing(values) {
            let spacing = false;

            // use the given constraint
            if (values.installment_spacing) {
                spacing = values.installment_spacing;
            }

            // calculate from the start date, end date, and # installments
            if (!spacing && typeof values.end_date !== 'undefined' && typeof values.num_installments !== 'undefined') {
                let start = moment(values.start_date);
                let end = moment(values.end_date);
                // compute diff in ms
                let diff = end.diff(start) / (values.num_installments - 1);
                // convert to moment Duration object
                spacing = moment.duration(diff);
            }

            // we have incomplete information
            if (spacing === false) {
                throw 'Incomplete information. Please add more constraints.';
            }

            // verify the computed amount (>= 1 day)
            if (spacing.asSeconds() < 86400) {
                throw 'Time between installments must be at least 1 day';
            }

            return spacing;
        }

        function applyInstallmentSpacing(schedule, values) {
            let curr = moment(schedule[0].date);

            // set the dates of the remaining installments
            for (let i = 1; i < schedule.length; i++) {
                curr.add(values.installment_spacing);

                // if this is the last installment then make sure it
                // matches the end date, when given
                if (i == schedule.length - 1 && values.end_date) {
                    schedule[i].date = moment(values.end_date).startOf('day').toDate();
                    break;
                }

                schedule[i].date = angular.copy(curr).startOf('day').toDate();
            }
        }

        function calcInstallmentAmount(values) {
            let amount = false;

            // use given constraint
            if (values.installment_amount) {
                amount = values.installment_amount;
            }

            // calculate from balance and # installments
            if (!amount && typeof values.total !== 'undefined' && typeof values.num_installments !== 'undefined') {
                amount = values.total / values.num_installments;
            }

            // we have incomplete information
            if (amount === false) {
                throw 'Incomplete information. Please add more constraints.';
            }

            // verify the computed amount
            amount = Money.round(values.currency, amount);
            if (isNaN(amount) || amount <= 0) {
                throw 'The installment amount must be a positive number.';
            }

            return amount;
        }

        function applyInstallmentAmount(schedule, values) {
            // set the amount of each installment
            let remaining = values.total;
            for (let i in schedule) {
                let installment = schedule[i];

                // the last installment is just the amount remaining
                if (i == schedule.length - 1) {
                    installment.amount = Money.round(values.currency, remaining);
                    remaining = 0;
                    break;
                }

                installment.amount = values.installment_amount;
                remaining -= installment.amount;
            }
        }

        function verify(schedule, constraints) {
            // check that there is at least one installment
            if (schedule.length === 0) {
                throw 'The schedule does not have any installments.';
            }

            // verify the installment length
            if (constraints.num_installments && schedule.length != constraints.num_installments) {
                throw 'The schedule does not have the required number of installments.';
            }

            // keep track of money amounts in cents because
            // floating point comparison in javascript is painful
            let total = 0;
            let amountConstraint = constraints.installment_amount
                ? Money.normalizeToZeroDecimal(constraints.currency, constraints.installment_amount)
                : false;
            let totalConstraint = constraints.total
                ? Money.normalizeToZeroDecimal(constraints.currency, constraints.total)
                : false;

            for (let i in schedule) {
                let installment = schedule[i];
                let amount = Money.normalizeToZeroDecimal(constraints.currency, installment.amount);
                if (amount <= 0) {
                    throw 'Installments can only have positive amounts.';
                }

                total += amount;

                // verify the installment amount
                // (except for the last installment)
                if (i == schedule.length - 1 && i > 0) {
                    continue;
                }

                if (amountConstraint && amount != amountConstraint) {
                    throw 'The installment amount(s) did not match the given constraint.';
                }
            }

            // verify the installments add up to the balance
            if (totalConstraint && total != totalConstraint) {
                throw 'The installment amounts did not add up to the balance.';
            }

            // verify the start date
            let first = schedule[0];
            if (constraints.start_date && !datesMatch(first.date, constraints.start_date)) {
                throw 'Start date does not match the given constraint.';
            }

            // verify the end date
            let last = schedule[schedule.length - 1];
            if (constraints.end_date && !datesMatch(last.date, constraints.end_date)) {
                throw 'End date does not match the given constraint.';
            }

            return true;
        }

        function datesMatch(a, b) {
            return moment(a).format('MM/dd/YYY') == moment(b).format('MM/dd/YYY');
        }
    }
})();
