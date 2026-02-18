/* jshint -W117, -W030 */
describe('money service', function () {
    'use strict';

    let Money;

    beforeEach(function () {
        module('app.core');

        inject(function (_Money_) {
            Money = _Money_;
        });
    });

    it('Produces a currency symbol', function () {
        expect(Money.currencySymbol('usd')).toEqual('$');
        expect(Money.currencySymbol('eur')).toEqual('€');
    });

    it('Gets number of decimals', function () {
        expect(Money.numDecimals(undefined)).toEqual(2);
        expect(Money.numDecimals('usd')).toEqual(2);
        expect(Money.numDecimals('eur')).toEqual(2);
        expect(Money.numDecimals('jpy')).toEqual(0);
    });

    it('Formats a money amount', function () {
        expect(Money.currencyFormat(100, 'usd')).toEqual('$100.00');
        expect(Money.currencyFormat(1000.24, 'usd')).toEqual('$1,000.24');
        expect(Money.currencyFormat(-123, 'usd')).toEqual('-$123.00');
        expect(Money.currencyFormat(1000.24, 'jpy')).toEqual('¥1,000');
        expect(Money.currencyFormat(1000.12345678, 'btc')).toEqual('BTC 1,000.12345678'); // jshint ignore:line

        let moneyFormat = {};

        expect(Money.currencyFormat(100, 'eur', moneyFormat)).toEqual('€100.00');
        expect(Money.currencyFormat(1000.24, 'eur', moneyFormat)).toEqual('€1,000.24');
    });

    it('Formats an currency amount as html', function () {
        expect(Money.currencyFormat(-100, 'usd', {}, true)).toEqual('-$100.00');
        expect(Money.currencyFormat(1000.24, 'usd', {}, true)).toEqual('$1,000.24');
        expect(Money.currencyFormat(1000.24, 'jpy', {}, true)).toEqual('¥1,000');
        expect(Money.currencyFormat(1000.12345678, 'btc', {}, true)).toEqual('BTC 1,000.12345678'); // jshint ignore:line
    });

    it('Formats a money amount with precision', function () {
        let moneyFormat = {
            precision: 4,
        };

        expect(Money.currencyFormat(100, 'usd', moneyFormat)).toEqual('$100.0000');
        expect(Money.currencyFormat(1000.24, 'usd', moneyFormat)).toEqual('$1,000.2400');
        expect(Money.currencyFormat(-123, 'usd', moneyFormat)).toEqual('-$123.0000');
        expect(Money.currencyFormat(1000.24, 'jpy', moneyFormat)).toEqual('¥1,000.2400');
        expect(Money.currencyFormat(1000.12345678, 'btc', moneyFormat)).toEqual('BTC 1,000.1235'); // jshint ignore:line
    });

    it('Formats a money amount with a currency code', function () {
        let moneyFormat = {
            use_symbol: false,
        };

        expect(Money.currencyFormat(100, 'usd', moneyFormat)).toEqual('USD 100.00'); // jshint ignore:line
        expect(Money.currencyFormat(1000.24, 'usd', moneyFormat)).toEqual('USD 1,000.24'); // jshint ignore:line
    });

    it('Normalizes a money amount to zero-decimal format', function () {
        expect(Money.normalizeToZeroDecimal('usd', 1.052)).toEqual(105);

        expect(Money.normalizeToZeroDecimal('xpf', 1.052)).toEqual(1);
    });

    it('Denormalizes a money amount from zero-decimal format', function () {
        expect(Money.denormalizeFromZeroDecimal('USD', 105)).toEqual(1.05);

        expect(Money.denormalizeFromZeroDecimal('BIF', 1)).toEqual(1);
    });

    it('Rounds a money amount', function () {
        expect(Money.round('usd', 1.051684)).toEqual(1.05);

        expect(Money.round('jpy', 1.151)).toEqual(1);

        expect(Money.round('btc', 1.123456789)).toEqual(1.12345679);
    });
});
