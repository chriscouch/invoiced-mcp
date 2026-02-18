/* jshint -W117, -W030 */
/* globals moment */

describe('subscription calculator service', function () {
    'use strict';

    let SubscriptionCalculator;

    let decimalFormat = {
        thousands_separator: ',',
        decimal_separator: '.',
    };

    // discounts
    let Discount1 = {
        id: 'discount_1',
        is_percent: true,
        value: 5,
    };

    // taxes
    let Tax1 = {
        id: 'tax_1',
        is_percent: true,
        value: 7,
    };

    beforeEach(function () {
        module('app.subscriptions');

        inject(function (_SubscriptionCalculator_) {
            SubscriptionCalculator = _SubscriptionCalculator_;
        });
    });

    describe('calculate', function () {
        it('should correctly calculate an empty subscription', function () {
            let subscription = {};
            let total = SubscriptionCalculator.calculate(subscription, decimalFormat);

            expect(total).toEqual(0);
        });

        it('should correctly calculate a subscription', function () {
            let subscription = {
                customer: {},
                plan: {
                    amount: 200,
                    currency: 'usd',
                },
                quantity: 2,
                addons: [
                    {
                        catalog_item: {
                            unit_cost: 99,
                        },
                        quantity: 1,
                    },
                    {
                        plan: {
                            amount: 199,
                        },
                        quantity: 1,
                    },
                ],
                taxes: [Tax1],
                discounts: [Discount1],
            };

            let total = SubscriptionCalculator.calculate(subscription, decimalFormat);

            expect(total).toEqual(709.52);
        });
    });

    describe('cyclesInDuration', function () {
        it('should correctly calculate a monthly plan', function () {
            expect(
                1,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'month', interval_count: 1 },
                    { interval: 'month', interval_count: 1 },
                ),
            );
            expect(
                3,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'month', interval_count: 3 },
                    { interval: 'month', interval_count: 1 },
                ),
            );
            expect(
                12,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'month', interval_count: 12 },
                    { interval: 'month', interval_count: 1 },
                ),
            );
            expect(
                12,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'year', interval_count: 1 },
                    { interval: 'month', interval_count: 1 },
                ),
            );
            expect(
                24,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'year', interval_count: 2 },
                    { interval: 'month', interval_count: 1 },
                ),
            );
        });

        it('should correctly calculate a quarterly plan', function () {
            expect(
                1,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'month', interval_count: 3 },
                    { interval: 'month', interval_count: 3 },
                ),
            );
            expect(
                4,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'month', interval_count: 12 },
                    { interval: 'month', interval_count: 3 },
                ),
            );
            expect(
                4,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'year', interval_count: 1 },
                    { interval: 'month', interval_count: 3 },
                ),
            );
            expect(
                8,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'year', interval_count: 2 },
                    { interval: 'month', interval_count: 3 },
                ),
            );
        });

        it('should correctly calculate a yearly plan', function () {
            expect(
                1,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'year', interval_count: 1 },
                    { interval: 'year', interval_count: 1 },
                ),
            );
            expect(
                2,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'year', interval_count: 2 },
                    { interval: 'year', interval_count: 1 },
                ),
            );
            expect(
                5,
                SubscriptionCalculator.cyclesInDuration(
                    { interval: 'year', interval_count: 5 },
                    { interval: 'year', interval_count: 1 },
                ),
            );
        });
    });

    describe('snapDayFuturePeriod', function () {
        let check = function (dates, subscription, expected) {
            for (let i in dates) {
                let calculated = SubscriptionCalculator.getSnappedDate(subscription, dates[i].clone());
                expect(expected[i].isSame(calculated)).toBe(
                    true,
                    dates[i].toString() + '/' + expected[i].toString() + '/' + calculated.toString(),
                );
            }
        };

        it('should correctly calculate a monthly period', function () {
            let dates = [
                moment('Jan 1 2023', 'MMM D YYYY'),
                moment('Jan 29 2023', 'MMM D YYYY'),
                moment('Jan 30 2023', 'MMM D YYYY'),
                moment('Jan 31 2023', 'MMM D YYYY'),
                moment('Feb 1 2023', 'MMM D YYYY'),
                moment('Feb 28 2023', 'MMM D YYYY'),
                moment('Apr 30 2023', 'MMM D YYYY'),
                moment('Dec 30 2023', 'MMM D YYYY'),
                moment('Dec 31 2023', 'MMM D YYYY'),
                moment('Jan 1 2024', 'MMM D YYYY'),
                moment('Jan 29 2024', 'MMM D YYYY'),
                moment('Jan 30 2024', 'MMM D YYYY'),
                moment('Jan 31 2024', 'MMM D YYYY'),
            ];

            let subscription = {
                snap_to_nth_day: 1,
                plan: {
                    interval: 'month',
                    interval_count: 1,
                },
            };
            let expected = [
                moment('Feb 1 2023', 'MMM D YYYY'),
                moment('Feb 1 2023', 'MMM D YYYY'),
                moment('Feb 1 2023', 'MMM D YYYY'),
                moment('Feb 1 2023', 'MMM D YYYY'),
                moment('Mar 1 2023', 'MMM D YYYY'),
                moment('Mar 1 2023', 'MMM D YYYY'),
                moment('May 1 2023', 'MMM D YYYY'),
                moment('Jan 1 2024', 'MMM D YYYY'),
                moment('Jan 1 2024', 'MMM D YYYY'),
                moment('Feb 1 2024', 'MMM D YYYY'),
                moment('Feb 1 2024', 'MMM D YYYY'),
                moment('Feb 1 2024', 'MMM D YYYY'),
                moment('Feb 1 2024', 'MMM D YYYY'),
            ];
            check(dates, subscription, expected);

            subscription = {
                snap_to_nth_day: 15,
                plan: {
                    interval: 'month',
                    interval_count: 1,
                },
            };
            expected = [
                moment('Jan 15 2023', 'MMM D YYYY'),
                moment('Feb 15 2023', 'MMM D YYYY'),
                moment('Feb 15 2023', 'MMM D YYYY'),
                moment('Feb 15 2023', 'MMM D YYYY'),
                moment('Feb 15 2023', 'MMM D YYYY'),
                moment('Mar 15 2023', 'MMM D YYYY'),
                moment('May 15 2023', 'MMM D YYYY'),
                moment('Jan 15 2024', 'MMM D YYYY'),
                moment('Jan 15 2024', 'MMM D YYYY'),
                moment('Jan 15 2024', 'MMM D YYYY'),
                moment('Feb 15 2024', 'MMM D YYYY'),
                moment('Feb 15 2024', 'MMM D YYYY'),
                moment('Feb 15 2024', 'MMM D YYYY'),
            ];
            check(dates, subscription, expected);

            subscription = {
                snap_to_nth_day: 31,
                plan: {
                    interval: 'month',
                    interval_count: 1,
                },
            };

            expected = [
                moment('Jan 31 2023', 'MMM D YYYY'),
                moment('Jan 31 2023', 'MMM D YYYY'),
                moment('Jan 31 2023', 'MMM D YYYY'),
                moment('Feb 28 2023', 'MMM D YYYY'),
                moment('Feb 28 2023', 'MMM D YYYY'),
                moment('Mar 31 2023', 'MMM D YYYY'),
                moment('May 31 2023', 'MMM D YYYY'),
                moment('Dec 31 2023', 'MMM D YYYY'),
                moment('Jan 31 2024', 'MMM D YYYY'),
                moment('Jan 31 2024', 'MMM D YYYY'),
                moment('Jan 31 2024', 'MMM D YYYY'),
                moment('Jan 31 2024', 'MMM D YYYY'),
                moment('Feb 29 2024', 'MMM D YYYY'),
            ];
            check(dates, subscription, expected);

            subscription = {
                snap_to_nth_day: 1,
                plan: {
                    interval: 'month',
                    interval_count: 2,
                },
            };
            expected = [
                moment('Mar 1 2023', 'MMM D YYYY'),
                moment('Mar 1 2023', 'MMM D YYYY'),
                moment('Mar 1 2023', 'MMM D YYYY'),
                moment('Mar 1 2023', 'MMM D YYYY'),
                moment('Apr 1 2023', 'MMM D YYYY'),
                moment('Apr 1 2023', 'MMM D YYYY'),
                moment('Jun 1 2023', 'MMM D YYYY'),
                moment('Feb 1 2024', 'MMM D YYYY'),
                moment('Feb 1 2024', 'MMM D YYYY'),
                moment('Mar 1 2024', 'MMM D YYYY'),
                moment('Mar 1 2024', 'MMM D YYYY'),
                moment('Mar 1 2024', 'MMM D YYYY'),
                moment('Mar 1 2024', 'MMM D YYYY'),
            ];
            check(dates, subscription, expected);

            subscription = {
                snap_to_nth_day: 15,
                plan: {
                    interval: 'month',
                    interval_count: 2,
                },
            };
            expected = [
                moment('Feb 15 2023', 'MMM D YYYY'),
                moment('Mar 15 2023', 'MMM D YYYY'),
                moment('Mar 15 2023', 'MMM D YYYY'),
                moment('Mar 15 2023', 'MMM D YYYY'),
                moment('Mar 15 2023', 'MMM D YYYY'),
                moment('Apr 15 2023', 'MMM D YYYY'),
                moment('Jun 15 2023', 'MMM D YYYY'),
                moment('Feb 15 2024', 'MMM D YYYY'),
                moment('Feb 15 2024', 'MMM D YYYY'),
                moment('Feb 15 2024', 'MMM D YYYY'),
                moment('Mar 15 2024', 'MMM D YYYY'),
                moment('Mar 15 2024', 'MMM D YYYY'),
                moment('Mar 15 2024', 'MMM D YYYY'),
            ];
            check(dates, subscription, expected);

            subscription = {
                snap_to_nth_day: 31,
                plan: {
                    interval: 'month',
                    interval_count: 2,
                },
            };
            expected = [
                moment('Feb 28 2023', 'MMM D YYYY'),
                moment('Feb 28 2023', 'MMM D YYYY'),
                moment('Feb 28 2023', 'MMM D YYYY'),
                moment('Mar 31 2023', 'MMM D YYYY'),
                moment('Mar 31 2023', 'MMM D YYYY'),
                moment('Apr 30 2023', 'MMM D YYYY'),
                moment('Jun 30 2023', 'MMM D YYYY'),
                moment('Jan 31 2024', 'MMM D YYYY'),
                moment('Feb 29 2024', 'MMM D YYYY'),
                moment('Feb 29 2024', 'MMM D YYYY'),
                moment('Feb 29 2024', 'MMM D YYYY'),
                moment('Feb 29 2024', 'MMM D YYYY'),
                moment('Mar 31 2024', 'MMM D YYYY'),
            ];
            check(dates, subscription, expected);
        });

        it('should correctly calculate a weekly period', function () {
            let subscription = {
                snap_to_nth_day: 1,
                plan: {
                    interval: 'week',
                    interval_count: 1,
                },
            };
            check([moment('Mar 29 2023')], subscription, [moment('Apr 3 2023')]);

            subscription = {
                snap_to_nth_day: 2,
                plan: {
                    interval: 'week',
                    interval_count: 1,
                },
            };
            check([moment('Mar 29 2023')], subscription, [moment('Apr 4 2023')]);

            subscription = {
                snap_to_nth_day: 3,
                plan: {
                    interval: 'week',
                    interval_count: 1,
                },
            };
            check([moment('Mar 29 2023')], subscription, [moment('Apr 5 2023')]);

            subscription = {
                snap_to_nth_day: 4,
                plan: {
                    interval: 'week',
                    interval_count: 1,
                },
            };
            check([moment('Mar 29 2023')], subscription, [moment('Mar 30 2023')]);

            subscription = {
                snap_to_nth_day: 1,
                plan: {
                    interval: 'week',
                    interval_count: 2,
                },
            };
            check([moment('Mar 29 2023')], subscription, [moment('Apr 10 2023')]);

            subscription = {
                snap_to_nth_day: 2,
                plan: {
                    interval: 'week',
                    interval_count: 2,
                },
            };
            check([moment('Mar 29 2023')], subscription, [moment('Apr 11 2023')]);

            subscription = {
                snap_to_nth_day: 3,
                plan: {
                    interval: 'week',
                    interval_count: 2,
                },
            };
            check([moment('Mar 29 2023')], subscription, [moment('Apr 12 2023')]);

            subscription = {
                snap_to_nth_day: 4,
                plan: {
                    interval: 'week',
                    interval_count: 2,
                },
            };
            check([moment('Mar 29 2023')], subscription, [moment('Apr 6 2023')]);
        });

        it('should correctly calculate a weekly period (Sunday)', function () {
            let subscription = {
                snap_to_nth_day: 6,
                plan: {
                    interval: 'week',
                    interval_count: 1,
                },
            };
            check([moment('Apr 2 2023')], subscription, [moment('Apr 8 2023')]);

            subscription = {
                snap_to_nth_day: 7,
                plan: {
                    interval: 'week',
                    interval_count: 1,
                },
            };
            check([moment('Apr 2 2023')], subscription, [moment('Apr 9 2023')]);

            subscription = {
                snap_to_nth_day: 1,
                plan: {
                    interval: 'week',
                    interval_count: 1,
                },
            };
            check([moment('Apr 2 2023')], subscription, [moment('Apr 3 2023')]);
        });

        it('should correctly calculate a yearly period', function () {
            let subscription = {
                snap_to_nth_day: 5,
                plan: {
                    interval: 'year',
                    interval_count: 1,
                },
            };
            let dates = [
                moment('Jan 1 2023', 'MMM D YYYY'),
                moment('Jan 5 2023', 'MMM D YYYY'),
                moment('Jan 10 2023', 'MMM D YYYY'),
            ];
            let expected = [
                moment('Jan 5 2023', 'MMM D YYYY'),
                moment('Jan 5 2024', 'MMM D YYYY'),
                moment('Jan 5 2024', 'MMM D YYYY'),
            ];
            check(dates, subscription, expected);

            subscription = {
                snap_to_nth_day: 5,
                plan: {
                    interval: 'year',
                    interval_count: 2,
                },
            };
            dates = [
                moment('Jan 1 2023', 'MMM D YYYY'),
                moment('Jan 5 2023', 'MMM D YYYY'),
                moment('Jan 10 2023', 'MMM D YYYY'),
            ];
            expected = [
                moment('Jan 5 2024', 'MMM D YYYY'),
                moment('Jan 5 2025', 'MMM D YYYY'),
                moment('Jan 5 2025', 'MMM D YYYY'),
            ];
            check(dates, subscription, expected);
        });
    });
});
