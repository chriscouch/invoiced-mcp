<?php

namespace App\Tests\PaymentProcessing\Models;

use App\Core\Utils\ModelNormalizer;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Refund;
use App\Tests\AppTestCase;
use Exception;

class ChargeTest extends AppTestCase
{
    private static Charge $charge;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasPayment();
    }

    public function testCreate(): void
    {
        self::$charge = new Charge();
        self::$charge->customer = self::$customer;
        self::$charge->payment = self::$payment;
        self::$charge->amount = self::$payment->amount;
        self::$charge->currency = self::$payment->currency;
        self::$charge->status = 'succeeded';
        self::$charge->gateway = StripeGateway::ID;
        self::$charge->gateway_id = 'test_id';
        self::$charge->last_status_check = time();
        self::$charge->saveOrFail();

        $this->assertEquals(self::$charge->customer_id, self::$customer->id());
        $this->assertEquals(self::$charge->payment_id, self::$payment->id());
        $this->assertEquals(self::$charge->customer, self::$customer);
        $this->assertEquals(self::$charge->payment, self::$payment);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'amount' => 200.0,
            'amount_refunded' => 0.0,
            'created_at' => self::$charge->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id(),
            'description' => null,
            'disputed' => false,
            'failure_message' => null,
            'gateway' => 'stripe',
            'gateway_id' => 'test_id',
            'id' => self::$charge->id(),
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => self::$payment->id(),
            'payment_source' => null,
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'succeeded',
            'updated_at' => self::$charge->updated_at,
        ];
        $this->assertEquals($expected, self::$charge->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$charge->status = 'failed';
        $this->assertTrue(self::$charge->save());
    }

    /**
     * @depends testCreate
     */
    public function testGetRefunds(): void
    {
        $refund = new Refund();
        $refund->charge = self::$charge;
        $refund->amount = self::$charge->amount;
        $refund->currency = self::$charge->currency;
        $refund->status = 'succeeded';
        $refund->gateway = self::$charge->gateway;
        $refund->gateway_id = 'ref_test';
        $refund->saveOrFail();

        $this->assertEquals($refund->charge_id, self::$charge->id());
        $this->assertCount(1, self::$charge->refresh()->refunds);
    }

    public function testEventObject(): void
    {
        $refund = self::$charge->refunds[0];
        $expected = [
            'amount' => 200.0,
            'amount_refunded' => 0.0,
            'created_at' => self::$charge->created_at,
            'currency' => 'usd',
            'customer' => ModelNormalizer::toArray(self::$customer),
            'customer_id' => self::$customer->id(),
            'description' => null,
            'disputed' => false,
            'failure_message' => null,
            'gateway' => 'stripe',
            'gateway_id' => 'test_id',
            'id' => self::$charge->id(),
            'merchant_account' => null,
            'merchant_account_id' => null,
            'merchant_account_transaction' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment' => self::$payment->id(),
            'payment_flow' => null,
            'payment_flow_id' => null,
            'payment_id' => self::$payment->id(),
            'payment_source' => null,
            'receipt_email' => null,
            'refunded' => false,
            'status' => 'failed',
            'updated_at' => self::$charge->updated_at,
            'refunds' => [
                [
                    'amount' => 200.0,
                    'created_at' => $refund->created_at,
                    'charge_id' => self::$charge->id,
                    'currency' => 'usd',
                    'failure_message' => null,
                    'gateway' => 'stripe',
                    'gateway_id' => 'ref_test',
                    'id' => $refund->id,
                    'status' => 'succeeded',
                    'updated_at' => $refund->updated_at,
                    'object' => 'refund',
                    'merchant_account_transaction_id' => null,
                ],
            ],
        ];
        $this->assertEquals($expected, self::$charge->getEventObject());

        $expected = [
            'amount' => 200.0,
            'created_at' => $refund->created_at,
            'currency' => 'usd',
            'failure_message' => null,
            'charge_id' => self::$charge->id,
            'charge' => [
                'amount' => 200.0,
                'amount_refunded' => 0.0,
                'created_at' => self::$charge->created_at,
                'currency' => 'usd',
                'customer' => ModelNormalizer::toArray(self::$customer),
                'customer_id' => self::$customer->id(),
                'description' => null,
                'disputed' => false,
                'failure_message' => null,
                'gateway' => 'stripe',
                'gateway_id' => 'test_id',
                'id' => self::$charge->id(),
                'merchant_account_transaction_id' => null,
                'object' => 'charge',
                'payment' => self::$payment->id(),
                'payment_id' => self::$payment->id(),
                'payment_source' => null,
                'receipt_email' => null,
                'refunded' => false,
                'status' => 'failed',
                'updated_at' => self::$charge->updated_at,
                'refunds' => [
                    [
                        'amount' => 200.0,
                        'created_at' => $refund->created_at,
                        'charge_id' => self::$charge->id,
                        'currency' => 'usd',
                        'failure_message' => null,
                        'gateway' => 'stripe',
                        'gateway_id' => 'ref_test',
                        'id' => $refund->id,
                        'status' => 'succeeded',
                        'updated_at' => $refund->updated_at,
                        'object' => 'refund',
                        'merchant_account_transaction_id' => null,
                    ],
                ],
                'merchant_account_id' => null,
                'merchant_account' => null,
                'merchant_account_transaction' => null,
                'payment_flow' => null,
                'payment_flow_id' => null,
            ],
            'gateway' => 'stripe',
            'gateway_id' => 'ref_test',
            'id' => $refund->id,
            'status' => 'succeeded',
            'updated_at' => $refund->updated_at,
            'object' => 'refund',
            'merchant_account_transaction_id' => null,
            'merchant_account_transaction' => null,
        ];
        $this->assertEquals($expected, $refund->getEventObject());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->expectException(Exception::class);

        // it should not be possible to delete charges
        self::$charge->delete();
    }
}
