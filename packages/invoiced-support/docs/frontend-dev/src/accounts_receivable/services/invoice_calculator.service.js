/* globals _ */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('InvoiceCalculator', InvoiceCalculator);

    InvoiceCalculator.$inject = ['Money'];

    function InvoiceCalculator(Money) {
        let rateTypes = ['discounts', 'taxes', 'shipping'];
        let lineItemRateTypes = ['discounts', 'taxes'];
        let rateObjectKeys = {
            discounts: 'coupon',
            taxes: 'tax_rate',
            shipping: 'shipping_rate',
        };
        let lineItemMax = 1000000000; // max $10M (in cents)

        let service = {
            calculate: calculate,
            calculateSubtotalLines: calculateSubtotalLines,
        };

        return service;

        function calculate(invoice, decimalFormat) {
            prepare(invoice, decimalFormat);
            calculateInvoice(invoice, decimalFormat);
            denormalize(invoice.currency, invoice);
        }

        function prepare(invoice, decimalFormat) {
            /* Line Items */

            angular.forEach(invoice.items, function (item) {
                // sanitize item
                item.discountable = typeof item.discountable === 'undefined' ? true : item.discountable;
                item.taxable = typeof item.taxable === 'undefined' ? true : item.taxable;

                item._quantity = parseFloat(
                    parseFormattedNumber(
                        '' + item.quantity,
                        decimalFormat.decimal_separator,
                        decimalFormat.thousands_separator,
                    ),
                );
                item._unit_cost = parseFloat(
                    parseFormattedNumber(
                        '' + item.unit_cost,
                        decimalFormat.decimal_separator,
                        decimalFormat.thousands_separator,
                    ),
                );

                // line items cannot exceed max
                if (item._unit_cost > lineItemMax) {
                    item._unit_cost = item.unit_cost = lineItemMax;
                }

                // expand rates
                angular.forEach(lineItemRateTypes, function (type) {
                    item[type] = expandRates(item[type], type);
                });
            });

            /* Subtotal */

            // expand rates
            angular.forEach(rateTypes, function (type) {
                invoice[type] = expandRates(invoice[type], type);
            });
        }

        function calculateInvoice(invoice, decimalFormat) {
            invoice.subtotal = 0;
            invoice.total = 0;

            let discountedSubtotal = 0;
            let discountExcluded = 0;
            let taxExcluded = 0;
            let overallRates = {
                discounts: {},
                taxes: {},
                shipping: {},
            };

            /* Line Items */

            let net, calculatedDiscounts, taxResult;
            angular.forEach(invoice.items, function (item) {
                // calculate line item amount
                item.amount = Money.normalizeToZeroDecimal(invoice.currency, item._quantity * item._unit_cost);
                if (isNaN(item.amount)) {
                    item.amount = 0;
                } else if (item.amount > lineItemMax) {
                    item.quantity = 1;
                }

                // determine amount excluded from discounts
                if (!item.discountable) {
                    discountExcluded += item.amount;
                }

                // determine amount exluded from taxes
                if (!item.taxable) {
                    taxExcluded += item.amount;
                }

                // apply discounts
                calculatedDiscounts = applyDiscounts(
                    invoice,
                    overallRates,
                    item.discounts,
                    item.amount,
                    true,
                    decimalFormat,
                );
                net = -calculatedDiscounts;

                // apply taxes
                taxResult = applyTaxes(
                    invoice,
                    overallRates,
                    item.taxes,
                    item.amount - calculatedDiscounts,
                    true,
                    decimalFormat,
                );
                net += taxResult[0];
                item.amount -= taxResult[1];

                discountedSubtotal += item.amount - calculatedDiscounts;
                invoice.subtotal += item.amount;
                invoice.total += net + item.amount;

                // clean up
                delete item._quantity;
                delete item._unit_cost;
            });

            /* Subtotal */

            // apply discounts
            calculatedDiscounts = applyDiscounts(
                invoice,
                overallRates,
                invoice.discounts,
                discountedSubtotal - discountExcluded,
                false,
                decimalFormat,
            );
            net = -calculatedDiscounts;

            // apply taxes
            taxResult = applyTaxes(
                invoice,
                overallRates,
                invoice.taxes,
                discountedSubtotal - calculatedDiscounts - taxExcluded,
                false,
                decimalFormat,
            );
            net += taxResult[0];

            // reduce the subtotal by the amount of tax calculated when tax inclusive pricing is used
            if (taxResult[1] > 0) {
                invoice.subtotal -= taxResult[1];
                invoice.total -= taxResult[1];
            }

            // apply shipping (deprecated)
            net += applyShipping(
                invoice,
                overallRates,
                invoice.shipping,
                discountedSubtotal - calculatedDiscounts,
                false,
                decimalFormat,
            );

            invoice.total += net;

            /* Order Overall Rates */

            angular.forEach(rateTypes, function (type) {
                overallRates[type] = _.toArray(overallRates[type]).sort(compareRates);
                angular.forEach(overallRates[type], function (appliedRate) {
                    delete appliedRate.order;
                });
            });

            invoice.rates = overallRates;

            /* Balance */

            // see if we need to calculate balance
            if (typeof invoice.amount_paid !== 'undefined') {
                let amountPaid = Money.normalizeToZeroDecimal(invoice.currency, parseFloat(invoice.amount_paid));
                if (isNaN(amountPaid) || amountPaid < 0) {
                    amountPaid = 0;
                }

                invoice.balance = invoice.total - amountPaid;
                invoice.amount_paid = amountPaid;
            }

            /* Total Rates */

            invoice.totals = {};
            angular.forEach(rateTypes, function (type) {
                invoice.totals[type] = 0;
                angular.forEach(invoice.rates[type], function (overallRate) {
                    invoice.totals[type] += overallRate.accumulated_total;
                });
            });
        }

        // found on http://stackoverflow.com/questions/281264/remove-empty-elements-from-an-array-in-javascript
        // removes empty elements from array
        function cleanArray(actual) {
            let newArray = [];
            for (let i = 0; i < actual.length; i++) {
                if (actual[i]) {
                    newArray.push(actual[i]);
                }
            }
            return newArray;
        }

        function expandRates(rates, type) {
            rates = cleanArray(_.toArray(angular.copy(rates)));

            angular.forEach(rates, function (rate, index) {
                // if a Rate object is given,
                // convert it to an Applied Rate like:
                // {coupon:{...}} and not {...}
                let k = rateObjectKeys[type];
                if (typeof rate[k] === 'undefined') {
                    rates[index] = {};
                    rates[index][k] = rate;
                }
            });

            return rates;
        }

        function applyDiscounts(invoice, overallRates, discounts, subtotal, appliedToItem, decimalFormat) {
            let calculatedDiscounts = 0;

            angular.forEach(discounts, function (discount) {
                discount.amount = calcAppliedRateAmount(
                    invoice.currency,
                    subtotal,
                    discount,
                    'discounts',
                    decimalFormat,
                );

                calculatedDiscounts += discount.amount;

                addToOverallRates(discount, 'discounts', overallRates.discounts, appliedToItem);
            });

            return calculatedDiscounts;
        }

        function applyTaxes(invoice, overallRates, taxes, subtotal, appliedToItem, decimalFormat) {
            let calculatedTaxes = 0;
            let totalMarkdown = 0;

            angular.forEach(taxes, function (tax) {
                let taxResult = calculateTaxAmount(invoice.currency, subtotal, tax, decimalFormat);
                tax.amount = taxResult[0];
                totalMarkdown += taxResult[1];

                calculatedTaxes += tax.amount;

                addToOverallRates(tax, 'taxes', overallRates.taxes, appliedToItem);
            });

            return [calculatedTaxes, totalMarkdown];
        }

        function calculateTaxAmount(currency, subtotal, appliedRate, decimalFormat) {
            if (typeof appliedRate._amount !== 'undefined') {
                appliedRate.amount = appliedRate._amount;
            }

            // check if there was a rate applied
            // TODO: the avalara check should happen elsewhere
            let taxAmount;
            if (
                typeof appliedRate.tax_rate !== 'undefined' &&
                appliedRate.tax_rate &&
                appliedRate.tax_rate.id != 'AVATAX'
            ) {
                // Handle tax inclusive pricing
                if (appliedRate.tax_rate.inclusive) {
                    taxAmount = calculateTaxInclusiveAmount(currency, subtotal, appliedRate.tax_rate, decimalFormat);

                    return [taxAmount, taxAmount];
                }

                // Handle tax exclusive pricing
                taxAmount = calculateTaxExclusiveAmount(currency, subtotal, appliedRate.tax_rate, decimalFormat);

                return [taxAmount, 0];
            }

            if (typeof appliedRate.amount !== 'undefined') {
                taxAmount = Money.normalizeToZeroDecimal(currency, appliedRate.amount);

                return [taxAmount, 0];
            }

            return [0, 0];
        }

        function calculateTaxInclusiveAmount(currency, amount, taxRate, decimalFormat) {
            let value = parseFloat(
                parseFormattedNumber(
                    '' + taxRate.value,
                    decimalFormat.decimal_separator,
                    decimalFormat.thousands_separator,
                ),
            );

            // On a percentage basis:
            // Subtotal = Total Amount / (1 + Tax Rate)               <-- Rounded Down
            // Tax Amount = Total Amount * Tax Rate / (1 + Tax Rate)  <-- Rounded Up
            // The tax amount is always rounded in favor of the tax agency.
            if (taxRate.is_percent) {
                let taxPercent = value / 100.0;
                return Math.ceil((amount * taxPercent) / (1 + taxPercent));
            }

            // Using a flat amount:
            // Subtotal = Total Amount - Tax Amount
            // Tax amount is known
            return Money.normalizeToZeroDecimal(currency, value);
        }

        function calculateTaxExclusiveAmount(currency, amount, taxRate, decimalFormat) {
            let value = parseFloat(
                parseFormattedNumber(
                    '' + taxRate.value,
                    decimalFormat.decimal_separator,
                    decimalFormat.thousands_separator,
                ),
            );

            if (taxRate.is_percent) {
                return parseInt(Math.round(Math.max(0, amount) * (value / 100.0)));
            }

            return Money.normalizeToZeroDecimal(currency, value);
        }

        function applyShipping(invoice, overallRates, shipping, subtotal, appliedToItem, decimalFormat) {
            let calculatedShipping = 0;

            angular.forEach(shipping, function (shipping2) {
                shipping2.amount = calcAppliedRateAmount(
                    invoice.currency,
                    subtotal,
                    shipping2,
                    'shipping',
                    decimalFormat,
                );

                calculatedShipping += shipping2.amount;

                addToOverallRates(shipping2, 'shipping', overallRates.shipping, appliedToItem);
            });

            return calculatedShipping;
        }

        function calcAppliedRateAmount(currency, amount, appliedRate, type, decimalFormat) {
            // when the amount comes from an input
            // (i.e. custom rates)
            // then we store the input on a separate property
            // to prevent the value from being formatted
            // while the user is typing but still
            // maintain calculation accuracy
            if (typeof appliedRate._amount !== 'undefined') {
                appliedRate.amount = appliedRate._amount;
            }

            let k = rateObjectKeys[type];

            if (appliedRate[k] && appliedRate[k].id != 'AVATAX') {
                return applyRateToAmount(currency, appliedRate[k], amount, decimalFormat);
            }

            if (appliedRate.amount) {
                return Money.normalizeToZeroDecimal(currency, appliedRate.amount);
            }

            return 0;
        }

        function applyRateToAmount(currency, rate, amount, decimalFormat) {
            let value = parseFloat(
                parseFormattedNumber(
                    '' + rate.value,
                    decimalFormat.decimal_separator,
                    decimalFormat.thousands_separator,
                ),
            );
            if (isNaN(value)) {
                value = 0;
            }

            if (rate.is_percent) {
                return Math.round(Math.max(0, amount) * (value / parseFloat(100)));
            }

            return Money.normalizeToZeroDecimal(currency, value);
        }

        function addToOverallRates(appliedRate, type, overallRates, appliedToItem) {
            let scope = appliedToItem ? 'items' : 'subtotal';
            let k = rateObjectKeys[type];
            let overallID = appliedRate[k] ? appliedRate[k].id : type;

            if (typeof overallRates[overallID] === 'undefined') {
                overallRates[overallID] = {
                    in_items: false,
                    in_subtotal: false,
                    accumulated_total: appliedRate.amount,
                    // keep track of when it was inserted for sorting later
                    order: Object.keys(overallRates).length + 1,
                };

                overallRates[overallID][k] = appliedRate[k];
            } else {
                overallRates[overallID].accumulated_total += appliedRate.amount;
            }

            overallRates[overallID]['in_' + scope] = true;
        }

        function compareRates(a, b) {
            /*
                - Order by scope:
                  1. Item-level
                  2. Subtotal-level

                - Order by original order as a last resort
            */

            // order by scope if present
            let levelA = a.in_subtotal - a.in_items;
            let levelB = b.in_subtotal - b.in_items;

            if (levelA != levelB) {
                return levelA > levelB ? 1 : -1;
            }

            // if the elements have the same score, use the order if present
            return typeof a.order !== 'undefined' && typeof b.order !== 'undefined' ? a.order - b.order : 0;
        }

        function parseFormattedNumber(val, decimal_separator, thousands_separator) {
            let dRegex = new RegExp('\\' + decimal_separator, 'g');
            let tRegex = new RegExp('\\' + thousands_separator, 'g');
            return val.toString().replace(tRegex, '').replace(dRegex, '.');
        }

        function denormalize(currency, invoice) {
            // denormalize line item amounts
            angular.forEach(invoice.items, function (item) {
                item.amount = Money.denormalizeFromZeroDecimal(currency, item.amount);

                // denormalize applied rate amounts
                angular.forEach(lineItemRateTypes, function (type) {
                    angular.forEach(item[type], function (appliedRate) {
                        appliedRate.amount = Money.denormalizeFromZeroDecimal(currency, appliedRate.amount);
                    });
                });
            });

            angular.forEach(rateTypes, function (type) {
                // denormalize applied rate amounts
                angular.forEach(invoice[type], function (appliedRate) {
                    appliedRate.amount = Money.denormalizeFromZeroDecimal(currency, appliedRate.amount);
                });

                // denormalize accumulated totals
                angular.forEach(invoice.rates[type], function (overallRate) {
                    overallRate.accumulated_total = Money.denormalizeFromZeroDecimal(
                        currency,
                        overallRate.accumulated_total,
                    );
                });
            });

            // denormalize totals
            invoice.subtotal = Money.denormalizeFromZeroDecimal(currency, invoice.subtotal);
            invoice.total = Money.denormalizeFromZeroDecimal(currency, invoice.total);

            if (typeof invoice.balance !== 'undefined') {
                invoice.amount_paid = Money.denormalizeFromZeroDecimal(currency, invoice.amount_paid);
                invoice.balance = Money.denormalizeFromZeroDecimal(currency, invoice.balance);
            }

            for (let i in invoice.totals) {
                invoice.totals[i] = Money.denormalizeFromZeroDecimal(invoice.currency, invoice.totals[i]);
            }
        }

        function calculateSubtotalLines(invoice) {
            let totalDiscounts = 0;
            angular.forEach(invoice.discounts, function (discount) {
                totalDiscounts += Money.normalizeToZeroDecimal(invoice.currency, discount.amount);
            });

            let totalTaxes = 0;
            angular.forEach(invoice.taxes, function (tax) {
                totalTaxes += Money.normalizeToZeroDecimal(invoice.currency, tax.amount);
            });

            let totalShipping = 0;
            angular.forEach(invoice.shipping, function (shipping) {
                totalShipping += Money.normalizeToZeroDecimal(invoice.currency, shipping.amount);
            });

            return {
                discounts: Money.denormalizeFromZeroDecimal(invoice.currency, totalDiscounts),
                taxes: Money.denormalizeFromZeroDecimal(invoice.currency, totalTaxes),
                shipping: Money.denormalizeFromZeroDecimal(invoice.currency, totalShipping),
            };
        }
    }
})();
