<?php

namespace App\Tests\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\RandomString;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\FlywireRefundApproveClient;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\Tests\AppTestCase;
use Mockery;

class FlywireGatewayTest extends AppTestCase
{
    private const SHARED_SECRET = 'test';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasMerchantAccount(FlywireGateway::ID, FlywireGateway::ID, ['shared_secret' => self::SHARED_SECRET]);
    }

    public function testChargeAmountsMismatch(): void
    {
        $this->expectException(ChargeException::class);
        $this->expectExceptionMessage('Amount mismatch.');

        $gateway = self::getService('test.payment_gateway_factory')->get(FlywireGateway::ID);
        $customer = self::$customer;

        $data = [
            'currency' => 'USD',
            'flywireAmount' => 100,
            'reference' => 'test',
            'status' => 'success',
            'paymentMethod' => 'credit_card',
            'token' => '1234',
        ];

        $merchantAccount = new MerchantAccount();

        $gateway->charge($customer, $merchantAccount, new Money('usd', 100), $data, '');
    }

    public function testCharge(): void
    {
        $gateway = self::getService('test.payment_gateway_factory')->get(FlywireGateway::ID);
        $nonce = RandomString::generate();

        $data = [
            'currency' => 'USD',
            'flywireAmount' => 100,
            'reference' => 'test',
            'status' => 'success',
            'paymentMethod' => 'credit_card',
            'nonce' => $nonce,
        ];

        $payload = $nonce.self::SHARED_SECRET.$data['reference'].$data['status'].$data['flywireAmount'].$data['paymentMethod'];
        $data['sig'] = hash('sha256', $payload);
        $amount = new Money('usd', 10000);

        $charge = $gateway->charge(self::$customer, self::$merchantAccount, $amount, $data, '');

        $this->assertEquals(self::$customer, $charge->customer);
        $this->assertEquals($amount, $charge->amount);
        $this->assertEquals(FlywireGateway::ID, $charge->gateway);
        $this->assertEquals($data['reference'], $charge->gatewayId);
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
    }

    public function testChargeWithTokenNoSignature(): void
    {
        $gateway = self::getService('test.payment_gateway_factory')->get(FlywireGateway::ID);

        $data = [
            'currency' => 'USD',
            'flywireAmount' => 100,
            'reference' => 'test',
            'status' => 'success',
            'paymentMethod' => 'credit_card',
            'token' => '1234',
            'type' => 'credit',
            'expirationYear' => '2030',
            'expirationMonth' => '3',
            'digits' => '1234',
            'brand' => 'MASTERCARD',
        ];
        $amount = new Money('usd', 10000);

        $charge = $gateway->charge(self::$customer, self::$merchantAccount, $amount, $data, '');

        $this->assertEquals(self::$customer, $charge->customer);
        $this->assertEquals($amount, $charge->amount);
        $this->assertEquals(FlywireGateway::ID, $charge->gateway);
        $this->assertEquals($data['reference'], $charge->gatewayId);
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
    }

    public function testChargeSourceBankAccount(): void
    {
        $client = Mockery::mock(FlywirePrivateClient::class);
        $approveClient = Mockery::mock(FlywireRefundApproveClient::class);

        $client->shouldReceive('pay')->andReturn([
            'reference' => 'ch_ach_test_2',
            'payerAmount' => 1,
            'amount' => 100,
            'status' => 'success',
            'external_reference' => 'test2',
        ]);

        $gateway = $this->getGateway($client, $approveClient);

        self::hasBankAccount('flywire');

        $invoice = new Invoice(['id' => -10]);
        $invoice->number = '1';
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';
        $invoice->date = time();

        $amount = new Money('usd', 10000);

        $parameters = [];

        $charge = $gateway->chargeSource(self::$bankAccount, $amount, $parameters, '', [$invoice]);

        $this->assertInstanceOf(ChargeValueObject::class, $charge);
        $this->assertEquals(self::$customer->id(), $charge->customer->id());
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
        $this->assertEquals('flywire', $charge->gateway);
        $this->assertEquals('ch_ach_test_2', $charge->gatewayId);
        $this->assertEquals(10000, $charge->amount->amount);
        $this->assertEquals('usd', $charge->amount->currency);
        $this->assertEquals(PaymentMethod::DIRECT_DEBIT, $charge->method);
        $this->assertInstanceOf(BankAccount::class, $charge->source);
        $this->assertEquals(self::$bankAccount->id(), $charge->source->id());
    }

    public function testChargeSourceCard(): void
    {
        $client = Mockery::mock(FlywirePrivateClient::class);
        $approveClient = Mockery::mock(FlywireRefundApproveClient::class);

        $client->shouldReceive('pay')->andReturn([
            'reference' => 'ch_cc_test_2',
            'payerAmount' => 1,
            'amount' => 100,
            'status' => 'success',
            'external_reference' => 'test2',
        ]);

        $gateway = $this->getGateway($client, $approveClient);

        self::hasCard('flywire');

        $invoice = new Invoice(['id' => -10]);
        $invoice->number = '1';
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';
        $invoice->date = time();

        $amount = new Money('usd', 10000);

        $parameters = [];

        $charge = $gateway->chargeSource(self::$card, $amount, $parameters, '', [$invoice]);

        $this->assertInstanceOf(ChargeValueObject::class, $charge);
        $this->assertEquals(self::$customer->id(), $charge->customer->id());
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
        $this->assertEquals('flywire', $charge->gateway);
        $this->assertEquals('ch_cc_test_2', $charge->gatewayId);
        $this->assertEquals(10000, $charge->amount->amount);
        $this->assertEquals('usd', $charge->amount->currency);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $charge->method);
        $this->assertInstanceOf(Card::class, $charge->source);
        $this->assertEquals(self::$card->id(), $charge->source->id());
    }

    public function testChargeSourceMakeDefault(): void
    {
        $client = Mockery::mock(FlywirePrivateClient::class);
        $approveClient = Mockery::mock(FlywireRefundApproveClient::class);

        $gateway = $this->getGateway($client, $approveClient);

        self::hasCard('flywire');

        $invoice = new Invoice(['id' => -10]);
        $invoice->number = '1';
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';
        $invoice->date = time();

        $amount = new Money('usd', 10000);

        $parameters = [
            'reference' => 'ch_save_payment_method',
            'payerAmount' => 1,
            'amount' => 100,
            'status' => 'success',
            'external_reference' => 'test_save_payment_method',
            'save_flywire_method' => true,
        ];

        $charge = $gateway->chargeSource(self::$card, $amount, $parameters, '', [$invoice]);

        $this->assertInstanceOf(ChargeValueObject::class, $charge);
        $this->assertEquals(self::$customer->id(), $charge->customer->id());
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
        $this->assertEquals('flywire', $charge->gateway);
        $this->assertEquals('ch_save_payment_method', $charge->gatewayId);
        $this->assertEquals(10000, $charge->amount->amount);
        $this->assertEquals('usd', $charge->amount->currency);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $charge->method);
        $this->assertInstanceOf(Card::class, $charge->source);
        $this->assertEquals(self::$card->id(), $charge->source->id());
    }

    public function testMakeLevel3(): void
    {
        $client = Mockery::mock(FlywirePrivateClient::class);
        $approveClient = Mockery::mock(FlywireRefundApproveClient::class);

        $gateway = $this->getGateway($client, $approveClient);

        $invoice = new Invoice(['id' => -10]);
        $invoice->number = '1';
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';
        $invoice->date = time();

        $documents = [$invoice];
        $expected = [
            'customer_reference' => 'flywire',
            'duty_amount' => 0,
            'shipping_amount' => 0,
            'total_tax_amount' => 1000,
            'total_discount_amount' => 0,
            'card_acceptor_tax_id' => null,
            'items' => [[
                'description' => 'Order Summary',
                'quantity' => 1,
                'unit_of_measure' => 'EA',
                'tax_amount' => 1000,
                'discount_amount' => 0,
                'unit_price' => 9000,
                'total_amount' => 9000,
                'total_amount_with_tax' => 10000
            ]],
        ];

        $level3 = $gateway->makeLevel3(self::$merchantAccount, self::$customer, Money::fromDecimal('usd', 100), $documents);
        $this->assertNotEmpty($level3['items'][0]['product_code']);
        unset($level3['items'][0]['product_code']);
        $this->assertEquals($expected, $level3);
    }

    public function testMakeLevel3NegativeAdjustment(): void
    {
        $client = Mockery::mock(FlywirePrivateClient::class);
        $approveClient = Mockery::mock(FlywireRefundApproveClient::class);

        $gateway = $this->getGateway($client, $approveClient);

        $invoice = new Invoice(['id' => -10]);
        $invoice->number = '1';
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';
        $invoice->total = 71.30;
        $invoice->items = [
            new LineItem(json_decode('
            {
                "amount": 70.8,
                "catalog_item": null,
                "created_at": 1754028140,
                "description": "",
                "discountable": true,
                "discounts": [],
                "id": 331530616,
                "metadata": {},
                "name": "Regulatory and Compliance for PAC",
                "object": "line_item",
                "quantity": 1,
                "taxable": true,
                "taxes": [],
                "type": null,
                "unit_cost": 70.8,
                "updated_at": 1754028140
            }
        ', true)),
            new LineItem(json_decode('
            {
                "amount": 22.18,
                "catalog_item": null,
                "created_at": 1754028140,
                "description": "",
                "discountable": true,
                "discounts": [],
                "id": 331530617,
                "metadata": {},
                "name": "Home Care Services",
                "object": "line_item",
                "quantity": 1,
                "taxable": true,
                "taxes": [],
                "type": null,
                "unit_cost": 22.18,
                "updated_at": 1754028140
            }
        ', true)),
            new LineItem(json_decode('
            {
                "amount": 46.5,
                "catalog_item": null,
                "created_at": 1754028140,
                "description": "",
                "discountable": true,
                "discounts": [],
                "id": 331530618,
                "metadata": {},
                "name": "Access Fee",
                "object": "line_item",
                "quantity": 1,
                "taxable": true,
                "taxes": [],
                "type": null,
                "unit_cost": 46.5,
                "updated_at": 1754028140
            }
        ', true)),

        ];
        $invoice->taxes = [json_decode('
            {
                "amount": 0.98,
                "id": 22374290,
                "updated_at": 1754028140,
                "object": "tax",
                "tax_rate": null
            }', true)
        ];
        $invoice->date = time();

        $documents = [$invoice];
        $expected = [
            'customer_reference' => 'flywire',
            'duty_amount' => 0,
            'shipping_amount' => 0,
            'total_tax_amount' => 98,
            'total_discount_amount' => 0,
            'card_acceptor_tax_id' => null,
            'items' => [
                [
                    'description' => 'Regulatory and Compliance for PAC',
                    'quantity' => 1,
                    'unit_of_measure' => 'EA',
                    'tax_amount' => 49,
                    'discount_amount' => 0,
                    'unit_price' => 7080,
                    'total_amount' => 7080,
                    'total_amount_with_tax' => 7129
                ],
                [
                    'description' => 'Home Care Services',
                    'quantity' => 1,
                    'unit_of_measure' => 'EA',
                    'tax_amount' => 15,
                    'discount_amount' => 0,
                    'unit_price' => 2218,
                    'total_amount' => 2218,
                    'total_amount_with_tax' => 2233,
                ],
                [
                    'description' => 'Access Fee',
                    'quantity' => 1,
                    'unit_of_measure' => 'EA',
                    'tax_amount' => 32,
                    'discount_amount' => 0,
                    'unit_price' => 4650,
                    'total_amount' => 4650,
                    'total_amount_with_tax' => 4682,
                ],
                [
                    'description' => 'Adjustment',
                    'quantity' => 1,
                    'unit_of_measure' => 'EA',
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'unit_price' => 2,
                    'total_amount' => 2,
                    'total_amount_with_tax' => 2,
                    'product_code' => 'Adjustment'
                ],
            ],
        ];

        $level3 = $gateway->makeLevel3(self::$merchantAccount, self::$customer, Money::fromDecimal('usd', 140.46), $documents);
        $this->assertNotEmpty($level3['items'][0]['product_code']);
        $this->assertNotEmpty($level3['items'][1]['product_code']);
        $this->assertNotEmpty($level3['items'][2]['product_code']);
        unset($level3['items'][0]['product_code']);
        unset($level3['items'][1]['product_code']);
        unset($level3['items'][2]['product_code']);
        $this->assertEquals($expected, $level3);
    }

    private function getGateway(FlywirePrivateClient $client, FlywireRefundApproveClient $refundClient): FlywireGateway
    {
        return new FlywireGateway($client, $refundClient);
    }
}
