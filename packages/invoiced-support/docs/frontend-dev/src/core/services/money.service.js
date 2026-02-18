(function () {
    'use strict';

    angular.module('app.core').factory('Money', Money);

    Money.$inject = ['InvoicedConfig'];

    function Money(InvoicedConfig) {
        let symbols = [];
        let decimals = {
            // Zero-decimal currencies:
            // https://en.wikipedia.org/wiki/ISO_4217
            BIF: 0,
            CLP: 0,
            DJF: 0,
            GNF: 0,
            JPY: 0,
            KMF: 0,
            KRW: 0,
            MGA: 0,
            PYG: 0,
            RWF: 0,
            VUV: 0,
            XAF: 0,
            XOF: 0,
            XPF: 0,
            // Bitcoin -> one satoshi has 8 decimal places
            BTC: 8,
        };

        let service = {
            currencySymbol: currencySymbol,
            numDecimals: numDecimals,
            currencyFormat: currencyFormat,
            normalizeToZeroDecimal: normalizeToZeroDecimal,
            denormalizeFromZeroDecimal: denormalizeFromZeroDecimal,
            round: roundCurrency,
        };

        return service;

        function currencySymbol(currency) {
            if (!currency) {
                return '';
            }

            currency = currency.toString().toLowerCase();
            if (typeof symbols[currency] === 'undefined') {
                if (typeof InvoicedConfig.currencies[currency] !== 'undefined') {
                    symbols[currency] = InvoicedConfig.currencies[currency].symbol;
                } else {
                    symbols[currency] = '';
                }
            }

            return symbols[currency];
        }

        function numDecimals(currency) {
            // currencies have 2 decimals by default
            if (!currency) {
                return 2;
            }

            currency = currency.toString().toUpperCase();

            // see if there is a preloaded decimal setting
            if (typeof decimals[currency] !== 'undefined') {
                return decimals[currency];
            }

            // currencies have 2 decimals by default
            return 2;
        }

        function currencyFormat(num, currency, options, html) {
            if (!currency) {
                return num;
            }

            options = options || {};
            html = html || false;

            options = angular.extend(
                {
                    use_symbol: true,
                },
                options,
            );

            if (typeof options.precision === 'undefined') {
                options.precision = numDecimals(currency);
            }

            let formatter = new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: currency.toString().toUpperCase(),
                minimumFractionDigits: options.precision,
                currencyDisplay: options.use_symbol ? 'symbol' : 'code',
            });

            return formatter.format(num);
        }

        function round(num, precision) {
            precision = precision || 0;
            return Math.round(num * Math.pow(10, precision)) / Math.pow(10, precision);
        }

        function normalizeToZeroDecimal(currency, amount) {
            let precision = numDecimals(currency);

            return round(amount * Math.pow(10, precision));
        }

        function denormalizeFromZeroDecimal(currency, amount) {
            let precision = numDecimals(currency);

            return round(amount / Math.pow(10, precision), precision);
        }

        function roundCurrency(currency, amount) {
            let precision = numDecimals(currency);

            return round(amount, precision);
        }
    }
})();
