<?php

namespace App\Tests\PaymentProcessing\Services;

use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class PaymentGatewayMetadataTest extends AppTestCase
{
    public function testTestGateway(): void
    {
        $gateways = PaymentGatewayMetadata::get();

        /* Currencies */

        $this->assertEquals(['usd'], $gateways->getSupportedCurrencies('test', PaymentMethod::ACH));
        $this->assertEquals('*', $gateways->getSupportedCurrencies('test', PaymentMethod::CREDIT_CARD));

        /* Minimum Amount */

        $amount = $gateways->getMinPaymentAmount('test', 'usd');
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(1, $amount->amount);
    }

    public function testPayPal(): void
    {
        $gateways = PaymentGatewayMetadata::get();

        /* Currencies */

        $this->assertEquals('*', $gateways->getSupportedCurrencies('paypal', PaymentMethod::PAYPAL));

        /* Minimum Amount */

        $amount = $gateways->getMinPaymentAmount('paypal', 'usd');
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(1, $amount->amount);
    }

    public function testStripe(): void
    {
        $gateways = PaymentGatewayMetadata::get();

        /* Currencies */

        // ach is accepted for usd only
        $this->assertEquals(['usd'], $gateways->getSupportedCurrencies('stripe', PaymentMethod::ACH));

        $expected = ['aed', 'afn', 'all', 'amd', 'ang', 'aoa', 'ars', 'aud', 'awg', 'azn', 'bam', 'bbd', 'bdt', 'bgn', 'bif', 'bmd', 'bnd', 'bob', 'brl', 'bsd', 'bwp', 'bzd', 'cad', 'cdf', 'chf', 'clp', 'cny', 'cop', 'crc', 'cve', 'czk', 'djf', 'dkk', 'dop', 'dzd', 'egp', 'etb', 'eur', 'fjd', 'fkp', 'gbp', 'gel', 'gip', 'gmd', 'gnf', 'gtq', 'gyd', 'hkd', 'hnl', 'hrk', 'htg', 'huf', 'idr', 'ils', 'inr', 'isk', 'jmd', 'jpy', 'kes', 'kgs', 'khr', 'kmf', 'krw', 'kyd', 'kzt', 'lak', 'lbp', 'lkr', 'lrd', 'lsl', 'mad', 'mdl', 'mga', 'mkd', 'mnt', 'mop', 'mro', 'mur', 'mvr', 'mwk', 'mxn', 'myr', 'mzn', 'nad', 'ngn', 'nio', 'nok', 'npr', 'nzd', 'pab', 'pen', 'pgk', 'php', 'pkr', 'pln', 'pyg', 'qar', 'ron', 'rsd', 'rub', 'rwf', 'sar', 'sbd', 'scr', 'sek', 'sgd', 'shp', 'sll', 'sos', 'srd', 'std', 'svc', 'szl', 'thb', 'tjs', 'top', 'try', 'ttd', 'twd', 'tzs', 'uah', 'ugx', 'usd', 'uyu', 'uzs', 'vnd', 'vuv', 'wst', 'xaf', 'xcd', 'xcg', 'xof', 'xpf', 'yer', 'zar', 'zmw'];
        $this->assertEquals($expected, $gateways->getSupportedCurrencies('stripe', PaymentMethod::CREDIT_CARD));

        /* Minimum Amount */

        $min = $gateways->getMinPaymentAmount('stripe', 'usd');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('usd', $min->currency);
        $this->assertEquals(50, $min->amount);

        $min = $gateways->getMinPaymentAmount('stripe', 'ngn');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('ngn', $min->currency);
        $this->assertEquals(1, $min->amount);
    }

    public function testAuthorizenet(): void
    {
        $gateways = PaymentGatewayMetadata::get();

        /* Currencies */

        // ach is accepted for usd only
        $this->assertEquals(['usd'], $gateways->getSupportedCurrencies('authorizenet', PaymentMethod::ACH));

        $expected = [
            'aud',
            'cad',
            'chf',
            'dkk',
            'eur',
            'gbp',
            'nok',
            'nzd',
            'pln',
            'sek',
            'usd',
        ];
        $this->assertEquals($expected, $gateways->getSupportedCurrencies('authorizenet', PaymentMethod::CREDIT_CARD));

        /* Minimum Amount */

        $min = $gateways->getMinPaymentAmount('authorizenet', 'usd');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('usd', $min->currency);
        $this->assertEquals(50, $min->amount);

        $min = $gateways->getMinPaymentAmount('authorizenet', 'cad');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('cad', $min->currency);
        $this->assertEquals(50, $min->amount);
    }

    public function testGoCardless(): void
    {
        $gateways = PaymentGatewayMetadata::get();

        /* Currencies */

        $this->assertEquals(['aud', 'cad', 'eur', 'gbp', 'nzd', 'sek', 'usd'], $gateways->getSupportedCurrencies('gocardless', PaymentMethod::DIRECT_DEBIT));

        /* Minimum Amount */

        $min = $gateways->getMinPaymentAmount('gocardless', 'gbp');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('gbp', $min->currency);
        $this->assertEquals(50, $min->amount);

        $min = $gateways->getMinPaymentAmount('gocardless', 'cad');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('cad', $min->currency);
        $this->assertEquals(50, $min->amount);

        $min = $gateways->getMinPaymentAmount('gocardless', 'eur');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('eur', $min->currency);
        $this->assertEquals(50, $min->amount);

        $min = $gateways->getMinPaymentAmount('gocardless', 'aud');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('aud', $min->currency);
        $this->assertEquals(50, $min->amount);

        $min = $gateways->getMinPaymentAmount('gocardless', 'sek');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('sek', $min->currency);
        $this->assertEquals(50, $min->amount);

        $min = $gateways->getMinPaymentAmount('gocardless', 'usd');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('usd', $min->currency);
        $this->assertEquals(50, $min->amount);
    }

    public function testFlywire(): void
    {
        $gateways = PaymentGatewayMetadata::get();

        /* Currencies */

        $this->assertEquals('*', $gateways->getSupportedCurrencies('flywire', PaymentMethodType::DirectDebit->toString()));
        $this->assertEquals('*', $gateways->getSupportedCurrencies('flywire', PaymentMethodType::Card->toString()));
        $this->assertEquals('*', $gateways->getSupportedCurrencies('flywire', PaymentMethodType::BankTransfer->toString()));
        $this->assertEquals('*', $gateways->getSupportedCurrencies('flywire', PaymentMethodType::Online->toString()));

        /* Minimum Amount */

        $min = $gateways->getMinPaymentAmount('flywire', 'gbp');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('gbp', $min->currency);
        $this->assertEquals(1, $min->amount);

        $min = $gateways->getMinPaymentAmount('flywire', 'cad');
        $this->assertInstanceOf(Money::class, $min);
        $this->assertEquals('cad', $min->currency);
        $this->assertEquals(1, $min->amount);
    }
}
