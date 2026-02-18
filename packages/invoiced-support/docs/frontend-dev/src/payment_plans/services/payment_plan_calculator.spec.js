/* jshint -W117, -W030 */
describe('payment plan calculator service', function () {
    'use strict';

    let PaymentPlanCalculator;

    beforeEach(function () {
        module('app.payment_plans');

        inject(function (_PaymentPlanCalculator_) {
            PaymentPlanCalculator = _PaymentPlanCalculator_;
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

    describe('build', function () {
        it('should produce an installment schedule for a given installment spacing and amount (#1)', function () {
            let constraints = {
                start_date: new Date(2016, 10, 12, 1, 2, 43),
                installment_amount: 15,
                installment_spacing: moment.duration(1, 'weeks'),
                total: 50,
                currency: 'usd',
            };

            let expected = [
                {
                    date: new Date(2016, 10, 12),
                    amount: 15,
                },
                {
                    date: new Date(2016, 10, 19),
                    amount: 15,
                },
                {
                    date: new Date(2016, 10, 26),
                    amount: 15,
                },
                {
                    date: new Date(2016, 11, 3),
                    amount: 5,
                },
            ];

            let schedule = PaymentPlanCalculator.build(constraints);
            expect(schedule).toEqualData(expected);
        });

        it('should produce an installment schedule for a given installment amount and end date (#2)', function () {
            let constraints = {
                start_date: new Date(2016, 10, 12, 1, 2, 43),
                installment_amount: 15,
                end_date: new Date(2016, 11, 3, 1, 2, 43),
                total: 50,
                currency: 'usd',
            };

            let expected = [
                {
                    date: new Date(2016, 10, 12),
                    amount: 15,
                },
                {
                    date: new Date(2016, 10, 19),
                    amount: 15,
                },
                {
                    date: new Date(2016, 10, 26),
                    amount: 15,
                },
                {
                    date: new Date(2016, 11, 3),
                    amount: 5,
                },
            ];

            let schedule = PaymentPlanCalculator.build(constraints);
            expect(schedule).toEqualData(expected);
        });

        it('should produce an installment schedule for a given # of installments and installment spacing (#3)', function () {
            let constraints = {
                start_date: new Date(2016, 10, 12, 1, 2, 43),
                num_installments: 4,
                installment_spacing: moment.duration(1, 'weeks'),
                total: 100,
                currency: 'usd',
            };

            let expected = [
                {
                    date: new Date(2016, 10, 12),
                    amount: 25,
                },
                {
                    date: new Date(2016, 10, 19),
                    amount: 25,
                },
                {
                    date: new Date(2016, 10, 26),
                    amount: 25,
                },
                {
                    date: new Date(2016, 11, 3),
                    amount: 25,
                },
            ];

            let schedule = PaymentPlanCalculator.build(constraints);
            expect(schedule).toEqualData(expected);
        });

        it('should produce an installment schedule for a given # of installments and end date (#4)', function () {
            let constraints = {
                start_date: new Date(2016, 10, 12, 1, 2, 43),
                end_date: new Date(2016, 11, 3, 1, 2, 43),
                num_installments: 4,
                total: 60.01,
                currency: 'usd',
            };

            let expected = [
                {
                    date: new Date(2016, 10, 12),
                    amount: 15,
                },
                {
                    date: new Date(2016, 10, 19),
                    amount: 15,
                },
                {
                    date: new Date(2016, 10, 26),
                    amount: 15,
                },
                {
                    date: new Date(2016, 11, 3),
                    amount: 15.01,
                },
            ];

            let schedule = PaymentPlanCalculator.build(constraints);
            expect(schedule).toEqualData(expected);
        });

        it('should produce an installment schedule for a given end date and installment spacing (#5)', function () {
            let constraints = {
                start_date: new Date(2016, 10, 12, 1, 2, 43),
                installment_spacing: moment.duration(1, 'weeks'),
                end_date: new Date(2016, 11, 3, 1, 2, 43),
                total: 100,
                currency: 'usd',
            };

            let expected = [
                {
                    date: new Date(2016, 10, 12),
                    amount: 25,
                },
                {
                    date: new Date(2016, 10, 19),
                    amount: 25,
                },
                {
                    date: new Date(2016, 10, 26),
                    amount: 25,
                },
                {
                    date: new Date(2016, 11, 3),
                    amount: 25,
                },
            ];

            let schedule = PaymentPlanCalculator.build(constraints);
            expect(schedule).toEqualData(expected);
        });
    });

    describe('verify', function () {
        it('should not verify a schedule with no installments', function () {
            let schedule = [];

            let constraints = {};

            expect(function () {
                PaymentPlanCalculator.verify(schedule, constraints);
            }).toThrow('The schedule does not have any installments.');
        });

        it('should verify a schedule with no constraints', function () {
            let schedule = [{}];

            let constraints = {};

            expect(PaymentPlanCalculator.verify(schedule, constraints)).toEqual(true);
        });

        it('should not verify a schedule with the wrong number of installments', function () {
            let schedule = [{}];

            let constraints = {
                num_installments: 2,
            };

            expect(function () {
                PaymentPlanCalculator.verify(schedule, constraints);
            }).toThrow('The schedule does not have the required number of installments.');
        });

        it('should not verify a schedule with non-positive installment amounts', function () {
            let schedule = [
                {
                    amount: 0,
                },
                {
                    amount: 15.01,
                },
                {
                    amount: 20,
                },
            ];

            let constraints = {};

            expect(function () {
                PaymentPlanCalculator.verify(schedule, constraints);
            }).toThrow('Installments can only have positive amounts.');
        });

        it('should not verify a schedule with non-matching installment amounts', function () {
            let schedule = [
                {
                    amount: 14.99,
                },
                {
                    amount: 15.01,
                },
                {
                    amount: 20,
                },
            ];

            let constraints = {
                installment_amount: 15,
                currency: 'usd',
            };

            expect(function () {
                PaymentPlanCalculator.verify(schedule, constraints);
            }).toThrow('The installment amount(s) did not match the given constraint.');
        });

        it('should not verify a schedule with a single non-matching installment amount', function () {
            let schedule = [
                {
                    amount: 500,
                },
            ];

            let constraints = {
                installment_amount: 100,
                currency: 'usd',
            };

            expect(function () {
                PaymentPlanCalculator.verify(schedule, constraints);
            }).toThrow('The installment amount(s) did not match the given constraint.');
        });

        it('should not verify a schedule with the wrong total', function () {
            let schedule = [
                {
                    amount: 14.99,
                },
                {
                    amount: 15.01,
                },
                {
                    amount: 20,
                },
            ];

            let constraints = {
                total: 100,
                currency: 'usd',
            };

            expect(function () {
                PaymentPlanCalculator.verify(schedule, constraints);
            }).toThrow('The installment amounts did not add up to the balance.');
        });

        it('should not verify a schedule with the wrong start date', function () {
            let schedule = [
                {
                    date: new Date(2016, 10, 13),
                },
            ];

            let constraints = {
                start_date: new Date(2016, 10, 12),
            };

            expect(function () {
                PaymentPlanCalculator.verify(schedule, constraints);
            }).toThrow('Start date does not match the given constraint.');
        });

        it('should not verify a schedule with the wrong start date', function () {
            let schedule = [
                {
                    date: new Date(2016, 10, 13),
                },
            ];

            let constraints = {
                end_date: new Date(2016, 10, 12),
            };

            expect(function () {
                PaymentPlanCalculator.verify(schedule, constraints);
            }).toThrow('End date does not match the given constraint.');
        });

        it('should verify a schedule with all of the correct constraints', function () {
            let schedule = [
                {
                    amount: 10.25,
                    date: new Date(2016, 10, 13),
                },
                {
                    amount: 10.25,
                    date: new Date(2016, 10, 14),
                },
                {
                    amount: 10.25,
                    date: new Date(2016, 10, 15),
                },
                {
                    amount: 10.25,
                    date: new Date(2016, 10, 16),
                },
                {
                    amount: 15,
                    date: new Date(2016, 10, 17),
                },
            ];

            let constraints = {
                num_installments: 5,
                total: 56,
                installment_amount: 10.25,
                start_date: new Date(2016, 10, 13, 1, 3, 42),
                end_date: new Date(2016, 10, 17, 1, 3, 42),
                currency: 'usd',
            };

            expect(PaymentPlanCalculator.verify(schedule, constraints)).toEqual(true);
        });
    });
});
