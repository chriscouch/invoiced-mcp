/* jshint -W117, -W030 */
describe('invoice calculator service', function () {
    'use strict';

    let InvoiceCalculator;

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

    let Discount2 = {
        id: 'discount_2',
        is_percent: false,
        value: 10,
    };

    let Discount3 = {
        id: 'discount_3',
        is_percent: false,
        value: 6,
    };

    // taxes
    let Tax1 = {
        id: 'tax_1',
        is_percent: true,
        value: 5,
    };

    let Tax2 = {
        id: 'tax_2',
        is_percent: true,
        value: 7,
    };

    let Tax3 = {
        id: 'tax_3',
        is_percent: true,
        value: 2,
    };

    let Tax4 = {
        id: 'tax_4',
        is_percent: true,
        value: 3,
    };

    let Tax5 = {
        id: 'tax_5',
        is_percent: true,
        value: 20,
        inclusive: true,
    };

    // shipping
    let Shipping1 = {
        id: 'shipping_1',
        is_percent: false,
        value: 5.29,
    };

    let Shipping2 = {
        id: 'shipping_2',
        is_percent: true,
        value: 6,
    };

    beforeEach(function () {
        module('app.accounts_receivable');

        inject(function (_InvoiceCalculator_) {
            InvoiceCalculator = _InvoiceCalculator_;
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

    describe('calculate', function () {
        it('should correctly calculate an empty invoice', function () {
            let invoice = {
                currency: 'usd',
                items: [],
                discounts: [],
                taxes: [],
                shipping: [],
            };

            InvoiceCalculator.calculate(invoice, decimalFormat);

            let expected = {
                currency: 'usd',
                items: [],
                subtotal: 0,
                discounts: [],
                taxes: [],
                shipping: [],
                rates: {
                    discounts: [],
                    taxes: [],
                    shipping: [],
                },
                total: 0,
                totals: {
                    discounts: 0,
                    taxes: 0,
                    shipping: 0,
                },
            };

            expect(invoice).toEqualData(expected);
        });

        it('should correctly calculate an invoice', function () {
            //
            // Setup
            //

            let invoice = {
                currency: 'usd',
                items: [],
                discounts: [],
                taxes: [],
                shipping: [],
                amount_paid: 10,
            };

            invoice.items = [
                {
                    name: '',
                    description: '',
                    quantity: 10,
                    unit_cost: 100,
                    amount: 1000,
                    discounts: [
                        Discount3, // $6
                    ],
                    taxes: [
                        Tax3, // 2%
                        Tax4, // 3%
                    ],
                },
                {
                    name: '',
                    description: 'test',
                    quantity: -1,
                    unit_cost: 15.58068,
                    amount: -15.58068,
                },
                {
                    name: '',
                    description: '',
                    quantity: 0,
                    unit_cost: 0,
                    amount: 0,
                    discounts: [
                        Discount1, // 5%
                        Discount2, // $10
                    ],
                },
                {
                    name: 'No taxes or discounts',
                    quantity: 2,
                    unit_cost: 99,
                    discountable: false,
                    taxable: false,
                },
            ];

            invoice.discounts = [
                Discount1, // 5%
                Discount2, // $10
                {
                    amount: 10,
                    coupon: null,
                },
            ];

            invoice.taxes = [
                Tax1, // 5%
                Tax2, // 7%
                {
                    amount: 5,
                    tax_rate: null,
                },
                {
                    amount: 7.2,
                    tax_rate: null,
                },
            ];

            invoice.shipping = [
                Shipping1, // $5.29
                Shipping2, // 6%
                {
                    amount: 10,
                    shipping_rate: null,
                },
            ];

            //
            // Calculate
            //

            InvoiceCalculator.calculate(invoice, decimalFormat);

            //
            // Verify
            //

            let subtotal = 1182.42;
            let excludedAmount = 198;
            expect(invoice.subtotal).toEqual(subtotal);

            let lineDiscounts = 6 + 10;
            let subtotalAfterLineDiscounts = subtotal - lineDiscounts;

            let lineTaxes = Math.round((1000 - 6) * 0.02 * 100) / 100;
            lineTaxes += Math.round((1000 - 6) * 0.03 * 100) / 100;

            let discountableSubtotal = subtotalAfterLineDiscounts - excludedAmount;
            let discounts = Math.round(discountableSubtotal * 0.05 * 100) / 100;
            discounts += 10 + 10;
            let discountedSubtotal = subtotalAfterLineDiscounts - discounts;

            let taxableSubtotal = discountedSubtotal - excludedAmount;
            let taxes = Math.round(taxableSubtotal * 0.05 * 100) / 100;
            taxes += Math.round(taxableSubtotal * 0.07 * 100) / 100;
            taxes += 5 + 7.2;

            let shipping = Math.round(discountedSubtotal * 0.06 * 100) / 100;
            shipping += 5.29 + 10;

            let total = Math.round((subtotal - lineDiscounts + lineTaxes - discounts + taxes + shipping) * 100) / 100;

            expect(invoice.total).toEqual(total);

            let balance = total - 10;
            expect(invoice.balance).toEqual(balance);

            expect(invoice.amount_paid).toEqual(10);

            let expectedItems = [
                {
                    name: '',
                    description: '',
                    quantity: 10,
                    unit_cost: 100,
                    amount: 1000,
                    discountable: true,
                    discounts: [
                        {
                            coupon: Discount3,
                            amount: 6,
                        },
                    ],
                    taxable: true,
                    taxes: [
                        {
                            tax_rate: Tax3,
                            amount: 19.88,
                        },
                        {
                            tax_rate: Tax4,
                            amount: 29.82,
                        },
                    ],
                },
                {
                    name: '',
                    description: 'test',
                    quantity: -1,
                    unit_cost: 15.58068,
                    amount: -15.58,
                    discountable: true,
                    discounts: [],
                    taxable: true,
                    taxes: [],
                },
                {
                    name: '',
                    description: '',
                    quantity: 0,
                    unit_cost: 0,
                    amount: 0,
                    discountable: true,
                    discounts: [
                        {
                            coupon: Discount1,
                            amount: 0,
                        },
                        {
                            coupon: Discount2,
                            amount: 10,
                        },
                    ],
                    taxable: true,
                    taxes: [],
                },
                {
                    name: 'No taxes or discounts',
                    quantity: 2,
                    unit_cost: 99,
                    amount: 198,
                    discountable: false,
                    discounts: [],
                    taxable: false,
                    taxes: [],
                },
            ];
            expect(invoice.items).toEqualData(expectedItems);

            let expectedDiscounts = [
                {
                    coupon: Discount1,
                    amount: 48.42,
                },
                {
                    coupon: Discount2,
                    amount: 10,
                },
                {
                    coupon: null,
                    amount: 10,
                },
            ];
            expect(invoice.discounts).toEqualData(expectedDiscounts);

            let expectedTaxes = [
                {
                    tax_rate: Tax1,
                    amount: 45.0,
                },
                {
                    tax_rate: Tax2,
                    amount: 63.0,
                },
                {
                    tax_rate: null,
                    amount: 5,
                },
                {
                    tax_rate: null,
                    amount: 7.2,
                },
            ];
            expect(invoice.taxes).toEqualData(expectedTaxes);

            let expectedShipping = [
                {
                    shipping_rate: Shipping1,
                    amount: 5.29,
                },
                {
                    shipping_rate: Shipping2,
                    amount: 65.88,
                },
                {
                    shipping_rate: null,
                    amount: 10,
                },
            ];
            expect(invoice.shipping).toEqualData(expectedShipping);

            let expectedRates = {
                discounts: [
                    {
                        coupon: Discount3,
                        in_items: true,
                        in_subtotal: false,
                        accumulated_total: 6,
                    },
                    {
                        coupon: Discount1,
                        in_items: true,
                        in_subtotal: true,
                        accumulated_total: 48.42,
                    },
                    {
                        coupon: Discount2,
                        accumulated_total: 20,
                        in_items: true,
                        in_subtotal: true,
                    },
                    {
                        coupon: null,
                        in_items: false,
                        in_subtotal: true,
                        accumulated_total: 10,
                    },
                ],
                taxes: [
                    {
                        tax_rate: Tax3,
                        in_items: true,
                        in_subtotal: false,
                        accumulated_total: 19.88,
                    },
                    {
                        tax_rate: Tax4,
                        in_items: true,
                        in_subtotal: false,
                        accumulated_total: 29.82,
                    },
                    {
                        tax_rate: Tax1,
                        in_items: false,
                        in_subtotal: true,
                        accumulated_total: 45.0,
                    },
                    {
                        tax_rate: Tax2,
                        in_items: false,
                        in_subtotal: true,
                        accumulated_total: 63.0,
                    },
                    {
                        tax_rate: null,
                        in_items: false,
                        in_subtotal: true,
                        accumulated_total: 12.2,
                    },
                ],
                shipping: [
                    {
                        shipping_rate: Shipping1,
                        in_items: false,
                        in_subtotal: true,
                        accumulated_total: 5.29,
                    },
                    {
                        shipping_rate: Shipping2,
                        in_items: false,
                        in_subtotal: true,
                        accumulated_total: 65.88,
                    },
                    {
                        shipping_rate: null,
                        in_items: false,
                        in_subtotal: true,
                        accumulated_total: 10,
                    },
                ],
            };
            expect(invoice.rates).toEqualData(expectedRates);

            let expectedTotals = {
                discounts: 84.42,
                taxes: 169.9,
                shipping: 81.17,
            };
            expect(invoice.totals).toEqualData(expectedTotals);
        });

        it('should correctly calculate a tax inclusive invoice', function () {
            //
            // Setup
            //

            let invoice = {
                currency: 'usd',
                items: [],
                discounts: [],
                taxes: [],
                shipping: [],
                amount_paid: 0,
            };

            invoice.items = [
                {
                    name: '',
                    description: '',
                    quantity: 10,
                    unit_cost: 100,
                    amount: 1000,
                    discounts: [],
                    taxes: [],
                    taxable: true,
                    discountable: true,
                },
                {
                    name: '',
                    description: '',
                    quantity: -1,
                    unit_cost: 15,
                    amount: -15,
                    discounts: [],
                    taxes: [],
                    taxable: true,
                    discountable: true,
                },
                {
                    name: '',
                    description: '',
                    quantity: 1,
                    unit_cost: 2000,
                    amount: 2000,
                    discounts: [],
                    taxes: [],
                    taxable: false,
                    discountable: true,
                },
            ];

            invoice.discounts = [];

            invoice.taxes = [
                Tax5, // 20%
            ];

            invoice.shipping = [];

            //
            // Calculate
            //

            InvoiceCalculator.calculate(invoice, decimalFormat);

            //
            // Verify
            //

            expect(invoice.subtotal).toEqual(2820.83);
            expect(invoice.total).toEqual(2985);
            expect(invoice.balance).toEqual(2985);
            expect(invoice.amount_paid).toEqual(0);

            let expectedItems = [
                {
                    name: '',
                    description: '',
                    quantity: 10,
                    unit_cost: 100,
                    amount: 1000,
                    discountable: true,
                    discounts: [],
                    taxable: true,
                    taxes: [],
                },
                {
                    name: '',
                    description: '',
                    quantity: -1,
                    unit_cost: 15,
                    amount: -15,
                    discountable: true,
                    discounts: [],
                    taxable: true,
                    taxes: [],
                },
                {
                    name: '',
                    description: '',
                    quantity: 1,
                    unit_cost: 2000,
                    amount: 2000,
                    discountable: true,
                    discounts: [],
                    taxable: false,
                    taxes: [],
                },
            ];
            expect(invoice.items).toEqualData(expectedItems);

            expect(invoice.discounts).toEqualData([]);

            let expectedTaxes = [
                {
                    tax_rate: Tax5,
                    amount: 164.17,
                },
            ];
            expect(invoice.taxes).toEqualData(expectedTaxes);

            expect(invoice.shipping).toEqualData([]);

            let expectedRates = {
                discounts: [],
                taxes: [
                    {
                        tax_rate: Tax5,
                        in_items: false,
                        in_subtotal: true,
                        accumulated_total: 164.17,
                    },
                ],
                shipping: [],
            };
            expect(invoice.rates).toEqualData(expectedRates);

            let expectedTotals = {
                discounts: 0,
                taxes: 164.17,
                shipping: 0,
            };
            expect(invoice.totals).toEqualData(expectedTotals);
        });

        it('should correctly calculate a tax inclusive invoice line item', function () {
            //
            // Setup
            //

            let invoice = {
                currency: 'usd',
                items: [],
                discounts: [],
                taxes: [],
                shipping: [],
                amount_paid: 0,
            };

            invoice.items = [
                {
                    name: '',
                    description: '',
                    quantity: 10,
                    unit_cost: 100,
                    amount: 1000,
                    discounts: [],
                    taxes: [Tax5],
                    taxable: true,
                    discountable: true,
                },
                {
                    name: '',
                    description: '',
                    quantity: 1,
                    unit_cost: 15,
                    amount: 15,
                    discounts: [],
                    taxes: [Tax4],
                    taxable: true,
                    discountable: true,
                },
                {
                    name: '',
                    description: '',
                    quantity: 1,
                    unit_cost: 2000,
                    amount: 2000,
                    discounts: [],
                    taxes: [],
                    taxable: true,
                    discountable: true,
                },
            ];

            invoice.discounts = [Discount1];

            invoice.taxes = [];

            invoice.shipping = [];

            //
            // Calculate
            //

            InvoiceCalculator.calculate(invoice, decimalFormat);

            //
            // Verify
            //

            expect(invoice.subtotal).toEqual(2848.33);
            expect(invoice.total).toEqual(2873.03);
            expect(invoice.balance).toEqual(2873.03);
            expect(invoice.amount_paid).toEqual(0);

            let expectedItems = [
                {
                    name: '',
                    description: '',
                    quantity: 10,
                    unit_cost: 100,
                    amount: 833.33,
                    discountable: true,
                    discounts: [],
                    taxable: true,
                    taxes: [
                        {
                            tax_rate: Tax5,
                            amount: 166.67,
                        },
                    ],
                },
                {
                    name: '',
                    description: '',
                    quantity: 1,
                    unit_cost: 15,
                    amount: 15,
                    discountable: true,
                    discounts: [],
                    taxable: true,
                    taxes: [
                        {
                            tax_rate: Tax4,
                            amount: 0.45,
                        },
                    ],
                },
                {
                    name: '',
                    description: '',
                    quantity: 1,
                    unit_cost: 2000,
                    amount: 2000,
                    discountable: true,
                    discounts: [],
                    taxable: true,
                    taxes: [],
                },
            ];
            expect(invoice.items).toEqualData(expectedItems);

            let expectedDiscounts = [
                {
                    coupon: Discount1,
                    amount: 142.42,
                },
            ];
            expect(invoice.discounts).toEqualData(expectedDiscounts);

            expect(invoice.taxes).toEqualData([]);
            expect(invoice.shipping).toEqualData([]);

            let expectedRates = {
                discounts: [
                    {
                        coupon: Discount1,
                        in_items: false,
                        in_subtotal: true,
                        accumulated_total: 142.42,
                    },
                ],
                taxes: [
                    {
                        tax_rate: Tax5,
                        in_items: true,
                        in_subtotal: false,
                        accumulated_total: 166.67,
                    },
                    {
                        tax_rate: Tax4,
                        in_items: true,
                        in_subtotal: false,
                        accumulated_total: 0.45,
                    },
                ],
                shipping: [],
            };
            expect(invoice.rates).toEqualData(expectedRates);

            let expectedTotals = {
                discounts: 142.42,
                taxes: 167.12,
                shipping: 0,
            };
            expect(invoice.totals).toEqualData(expectedTotals);
        });
    });

    describe('calculateSubtotalLines', function () {
        //
        // Setup
        //

        let invoice = {
            currency: 'usd',
            discounts: [
                {
                    amount: 48.42,
                    coupon: Discount1,
                },
                {
                    amount: 10,
                    coupon: Discount2,
                },
                {
                    amount: 6,
                    coupon: Discount3,
                },
                {
                    amount: 10,
                    coupon: null,
                },
            ],
            taxes: [
                {
                    amount: 45.0,
                    tax_rate: Tax1,
                },
                {
                    amount: 63.0,
                    tax_rate: Tax2,
                },
                {
                    amount: 19.88,
                    tax_rate: Tax3,
                },
                {
                    amount: 29.82,
                    tax_rate: Tax4,
                },
                {
                    amount: 5,
                    tax_rate: null,
                },
                {
                    amount: 7.2,
                    tax_rate: null,
                },
            ],
            shipping: [
                {
                    amount: 5.29,
                    shipping_rate: Shipping1,
                },
                {
                    amount: 65.88,
                    shipping_rate: Shipping2,
                },
                {
                    amount: 10,
                    shipping_rate: null,
                },
            ],
        };

        //
        // Calculate
        //

        let result;
        it('should calculate result', function () {
            result = InvoiceCalculator.calculateSubtotalLines(invoice);
            expect(typeof result).toBe('object');
        });

        //
        // Verify
        //

        it('should return correctly calculated subtotals', function () {
            let expected = {
                discounts: 74.42,
                taxes: 169.9,
                shipping: 81.17,
            };

            expect(result).toEqualData(expected);
        });
    });
});
