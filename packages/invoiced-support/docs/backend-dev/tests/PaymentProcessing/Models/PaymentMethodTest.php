<?php

namespace App\Tests\PaymentProcessing\Models;

use App\Companies\Models\Company;
use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use InvalidArgumentException;

class PaymentMethodTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testEnabledCreditCard(): void
    {
        $cc = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $this->assertFalse($cc->enabled());
    }

    public function testEnabledACH(): void
    {
        $ach = PaymentMethod::instance(self::$company, PaymentMethod::ACH);
        $this->assertFalse($ach->enabled());
    }

    public function testEnabledPayPal(): void
    {
        $paypal = PaymentMethod::instance(self::$company, PaymentMethod::PAYPAL);
        $this->assertFalse($paypal->enabled());

        $paypal->meta = 'paypal@example.com';
        $paypal->enabled = true;
        $this->assertTrue($paypal->enabled());
    }

    public function testEnabledCheck(): void
    {
        $check = PaymentMethod::instance(self::$company, PaymentMethod::CHECK);
        $this->assertFalse($check->enabled());

        $check->enabled = true;
        $this->assertFalse($check->enabled());

        $check->meta = 'Instructions...';
        $this->assertTrue($check->enabled());
    }

    public function testEnabledCash(): void
    {
        $cash = PaymentMethod::instance(self::$company, PaymentMethod::CASH);
        $cash->enabled = false;
        $this->assertFalse($cash->enabled());
    }

    public function testToString(): void
    {
        $creditCardMethod = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $this->assertEquals('Card', $creditCardMethod->toString());
    }

    public function testGetMerchantAccount(): void
    {
        $company = new Company(['id' => -1]);
        $account = new MerchantAccount();
        $method = PaymentMethod::instance($company, PaymentMethod::CREDIT_CARD);
        $method->gateway = 'authorizenet';
        $method->merchant_account_id = 10;
        $method->setRelation('merchant_account', $account);
        $this->assertEquals($account, $method->getDefaultMerchantAccount());
    }

    public function testGetMerchantAccountMockGateway(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::CREDIT_CARD);
        $method->gateway = MockGateway::ID;
        $this->assertInstanceOf(MerchantAccount::class, $method->getDefaultMerchantAccount());
    }

    public function testGetMerchantAccountPayPal(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::PAYPAL);
        $method->gateway = PaymentGatewayMetadata::PAYPAL;
        $this->assertInstanceOf(MerchantAccount::class, $method->getDefaultMerchantAccount());
    }

    public function testGetMerchantAccountTestGateway(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::CREDIT_CARD);
        $method->gateway = TestGateway::ID;
        $this->assertInstanceOf(MerchantAccount::class, $method->getDefaultMerchantAccount());
    }

    public function testGetMerchantAccountNoGateway(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::CHECK);
        $this->assertInstanceOf(MerchantAccount::class, $method->getDefaultMerchantAccount());
    }

    public function testGetMerchantAccountMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::CREDIT_CARD);
        $method->gateway = StripeGateway::ID;
        $method->getDefaultMerchantAccount();
    }

    public function testGetMerchantAccountFail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::CREDIT_CARD);
        $method->gateway = 'stripe';
        $method->getDefaultMerchantAccount();
    }

    public function testSupportsAutoPay(): void
    {
        $company = new Company(['id' => -1]);

        $method = PaymentMethod::instance($company, PaymentMethod::PAYPAL);
        $method->gateway = PaymentGatewayMetadata::PAYPAL;
        $this->assertFalse($method->supportsAutoPay());

        $method = PaymentMethod::instance($company, PaymentMethod::CHECK);
        $this->assertFalse($method->supportsAutoPay());

        $method = PaymentMethod::instance($company, PaymentMethod::CREDIT_CARD);
        $method->gateway = StripeGateway::ID;
        $this->assertTrue($method->supportsAutoPay());

        $method = PaymentMethod::instance($company, PaymentMethod::ACH);
        $method->gateway = StripeGateway::ID;
        $this->assertTrue($method->supportsAutoPay());

        $method = PaymentMethod::instance($company, PaymentMethod::DIRECT_DEBIT);
        $method->gateway = GoCardlessGateway::ID;
        $this->assertTrue($method->supportsAutoPay());
    }

    public function testEdit(): void
    {
        $paypal = PaymentMethod::instance(self::$company, PaymentMethod::PAYPAL);

        $paypal->enabled = true;
        $paypal->meta = 'paypal@test.com';
        $this->assertTrue($paypal->save());
        $this->assertTrue($paypal->enabled());

        $paypal->enabled = false;
        $this->assertTrue($paypal->save());
        $this->assertFalse($paypal->enabled());
    }

    /**
     * @depends testEdit
     */
    public function testToArray(): void
    {
        // since the payment methods are already created in
        // the company's afterCreate event these should just work

        $creditCard = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);

        $expected = [
            'id' => PaymentMethod::CREDIT_CARD,
            'name' => null,
            'enabled' => false,
            'order' => 0,
            'meta' => null,
            'gateway' => null,
            'merchant_account' => null,
            'min' => null,
            'max' => null,
            'created_at' => $creditCard->created_at,
            'updated_at' => $creditCard->updated_at,
            'convenience_fee' => 0,
        ];

        $this->assertEquals($expected, $creditCard->toArray());

        $paypal = PaymentMethod::instance(self::$company, PaymentMethod::PAYPAL);

        $expected = [
            'id' => PaymentMethod::PAYPAL,
            'name' => null,
            'enabled' => false,
            'order' => 0,
            'meta' => 'paypal@test.com',
            'gateway' => PaymentGatewayMetadata::PAYPAL,
            'merchant_account' => null,
            'min' => null,
            'max' => null,
            'created_at' => $paypal->created_at,
            'updated_at' => $paypal->updated_at,
            'convenience_fee' => 0,
        ];

        $this->assertEquals($expected, $paypal->toArray());
    }

    public function testQuery(): void
    {
        $methods = PaymentMethod::all();

        // check that the returned models have the right payment types
        $expectedTypes = [
            PaymentMethod::CREDIT_CARD,
            PaymentMethod::ACH,
            PaymentMethod::PAYPAL,
            PaymentMethod::CHECK,
            PaymentMethod::WIRE_TRANSFER,
            PaymentMethod::CASH,
            PaymentMethod::OTHER,
        ];

        foreach ($methods as $method) {
            $expectedTypes = array_diff($expectedTypes, [$method->id]);
        }

        $this->assertEquals([], $expectedTypes);
    }

    public function testAllEnabled(): void
    {
        $paypal = PaymentMethod::instance(self::$company, PaymentMethod::PAYPAL);

        $paypal->enabled = true;
        $this->assertTrue($paypal->save());

        $methods = PaymentMethod::allEnabled(self::$company);

        $included = array_map(function ($method) {
            return $method->id;
        }, $methods);

        $expected = [PaymentMethod::PAYPAL => PaymentMethod::PAYPAL];

        $this->assertEquals($expected, $included);
    }

    public function testAllEnabledSorting(): void
    {
        $cash = PaymentMethod::instance(self::$company, PaymentMethod::CASH);
        $cash->order = 2;
        $cash->enabled = true;
        $cash->meta = 'Instructions...';
        $cash->save();

        $check = PaymentMethod::instance(self::$company, PaymentMethod::CHECK);
        $check->order = 1;
        $check->enabled = true;
        $check->meta = 'Instructions...';
        $check->save();

        $ach = PaymentMethod::instance(self::$company, PaymentMethod::ACH);
        $ach->order = 1;
        $ach->enabled = true;
        $ach->gateway = TestGateway::ID;
        $ach->save();

        $card = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $card->enabled = true;
        $card->gateway = TestGateway::ID;
        $card->save();

        $methods = PaymentMethod::allEnabled(self::$company);

        $included = array_map(function ($method) {
            return $method->id;
        }, $methods);
        $ordering = array_keys($included);

        $expected = [
            PaymentMethod::CASH,
            PaymentMethod::ACH,
            PaymentMethod::CHECK,
            PaymentMethod::CREDIT_CARD,
            PaymentMethod::PAYPAL,
        ];

        $this->assertEquals($expected, $ordering);
    }

    public function testAcceptsPayments(): void
    {
        $this->assertFalse(PaymentMethod::acceptsPayments(new Company()));
        $this->assertTrue(PaymentMethod::acceptsPayments(self::$company));
    }

    public function testDelete(): void
    {
        $paypal = PaymentMethod::instance(self::$company, PaymentMethod::PAYPAL);
        $this->assertFalse($paypal->delete());
    }

    public function testCreateInvalidMinMax(): void
    {
        $paypal = PaymentMethod::instance(self::$company, PaymentMethod::PAYPAL);
        $paypal->min = -1;

        $this->assertFalse($paypal->save());
        $this->assertEquals('Minimum cannot be less than 0.', $paypal->getErrors()[0]['message']);

        $paypal->min = 0;
        $paypal->max = -1;

        $this->assertFalse($paypal->save());
        $this->assertEquals('Maximum cannot be less than 0.', $paypal->getErrors()[0]['message']);

        $paypal->max = 1000000001;

        $this->assertFalse($paypal->save());
        $this->assertEquals('Maximum can be no more than 10000000.', $paypal->getErrors()[0]['message']);

        $paypal->min = 10;
        $paypal->max = 10;

        $this->assertFalse($paypal->save());
        $this->assertEquals('Minimum must be less than maximum.', $paypal->getErrors()[0]['message']);

        $paypal->min = 10;
        $paypal->max = 9;

        $this->assertFalse($paypal->save());
        $this->assertEquals('Minimum must be less than maximum.', $paypal->getErrors()[0]['message']);
    }
}
