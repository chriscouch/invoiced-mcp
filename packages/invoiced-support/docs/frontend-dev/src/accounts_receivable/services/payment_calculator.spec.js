/* jshint -W117, -W030 */
describe('payment calculator service', function () {
    'use strict';

    let PaymentCalculator;

    let selectedCompany;

    let Invoice1 = {
        id: 1,
    };

    let Invoice2 = {
        id: 2,
    };

    let Invoice3 = {
        id: 3,
    };

    let CreditNote1 = {
        id: 4,
    };

    beforeEach(function () {
        module('app.accounts_receivable');

        inject(function (_PaymentCalculator_, _selectedCompany_) {
            PaymentCalculator = _PaymentCalculator_;
            selectedCompany = _selectedCompany_;
        });

        jasmine.addMatchers({
            toEqualData: function () {
                return {
                    compare: function (actual, expected) {
                        let result = {};
                        result.pass = angular.equals(actual, expected);

                        if (result.pass) {
                            result.message =
                                'Expected this:\n' +
                                JSON.stringify(actual, null, 2) +
                                '\nto not match this:\n' +
                                JSON.stringify(expected, null, 2);
                        } else {
                            result.message =
                                'Expected this:\n' +
                                JSON.stringify(actual, null, 2) +
                                '\nto match this:\n' +
                                JSON.stringify(expected, null, 2);
                        }

                        return result;
                    },
                };
            },
        });
    });

    describe('calculateRemaining', function () {
        it('should calculate total applied and amount remaining', function () {
            let applied = [
                {
                    amount: -100,
                },
                {
                    amount: 10.003,
                },
                {
                    amount: 0.00005,
                },
                {
                    amount: 80,
                },
            ];
            let availableCredits = [
                {
                    amount: 0,
                },
                {
                    amount: 123,
                },
            ];

            // returns [totalApplied, remaining, totalCredits]
            let remaining = PaymentCalculator.calculateRemaining(100, applied, availableCredits, 'usd');

            expect(remaining).toEqual([90, 133, 123]);
        });
    });

    describe('calculateTree', function () {
        it('should calculate a transaction tree', function () {
            let payment2 = {
                type: 'payment',
                amount: 200,
                currency: 'usd',
                invoice: Invoice2,
                credit_note: null,
            };

            let adjustment = {
                type: 'adjustment',
                amount: -400,
                currency: 'usd',
                invoice: null,
                credit_note: CreditNote1,
            };

            let payment4 = {
                type: 'payment',
                amount: 500,
                currency: 'usd',
                invoice: Invoice3,
                credit_note: null,
            };

            let refund = {
                amount: 600,
                type: 'refund',
                invoice: 1234,
                credit_note: null,
            };

            let payment5 = {
                type: 'charge',
                amount: 600,
                currency: 'usd',
                children: [refund],
            };

            let payment3 = {
                type: 'payment',
                amount: 300,
                currency: 'usd',
                invoice: Invoice3,
                credit_note: null,
                children: [adjustment, payment4, payment5],
            };

            let payment1 = {
                type: 'payment',
                amount: 100,
                currency: 'usd',
                invoice: Invoice1,
                credit_note: null,
                children: [payment2, payment3],
            };

            let tree = payment1;

            let result = PaymentCalculator.calculateTree(tree);

            let expected = {
                paid: 1700,
                credited: 400,
                refunded: 600,
                net: 1100,
                appliedTo: [payment1, payment2, payment3, adjustment, payment4, payment5, refund],
            };

            expect(result).toEqual(expected);
        });
    });

    describe('validateAmount', function () {
        it('should return false for invalid payment amounts', function () {
            let valid = PaymentCalculator.validateAmount(0, 0);
            expect(valid).toBe(false);

            valid = PaymentCalculator.validateAmount(-100, 0);
            expect(valid).toBe(false);
        });

        it('should return true for valid payment amounts', function () {
            let valid = PaymentCalculator.validateAmount(null, 0);
            expect(valid).toBe(true);

            valid = PaymentCalculator.validateAmount(100, 0);
            expect(valid).toBe(true);

            valid = PaymentCalculator.validateAmount(100, 100);
            expect(valid).toBe(true);
        });

        it('should return false for zero with no credits', function () {
            let valid = PaymentCalculator.validateAmount(0, 0);
            expect(valid).toBe(false);
        });
    });

    describe('validateSplits', function () {
        it('should validate line amounts', function () {
            let applied = [
                {
                    amount: -100,
                },
                {
                    amount: 0,
                },
                {
                    amount: 100,
                },
                {
                    invoice: {
                        balance: 100,
                    },
                    amount: 101,
                },
                {
                    amount: null,
                },
                {
                    amount: '',
                },
            ];

            let valid = PaymentCalculator.validateAppliedTo(applied);
            expect(valid).toBe(false);

            // validate each line
            expect(applied[0].invalid).toBe(true);
            expect(applied[0].over).toBe(false);

            expect(applied[1].invalid).toBe(true);
            expect(applied[1].over).toBe(false);

            expect(applied[2].invalid).toBe(false);
            expect(applied[2].over).toBe(false);

            expect(applied[3].invalid).toBe(true);
            expect(applied[3].over).toBe(true);

            expect(applied[4].invalid).toBe(false);
            expect(applied[4].over).toBe(false);

            expect(applied[5].invalid).toBe(false);
            expect(applied[5].over).toBe(false);
        });
    });
});
