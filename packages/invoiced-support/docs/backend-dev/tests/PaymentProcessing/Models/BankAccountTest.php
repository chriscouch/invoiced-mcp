<?php

namespace App\Tests\PaymentProcessing\Models;

use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\PaymentMethod;

class BankAccountTest extends PaymentSourceTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::acceptsACH();
    }

    public function getModel(): string
    {
        return BankAccount::class;
    }

    public function expectedMethod(): string
    {
        return PaymentMethod::ACH;
    }

    public function expectedTypeName(): string
    {
        return 'Bank Account';
    }

    public function getCreateParams(): array
    {
        return [
            'bank_name' => 'Chase',
            'last4' => 1234,
            'routing_number' => '1110000000',
            'currency' => 'usd',
            'country' => 'US',
            'gateway_id' => 'ba_test',
            'chargeable' => true,
        ];
    }

    public function expectedArray(): array
    {
        return [
            'id' => self::$source->id(),
            'object' => 'bank_account',
            'bank_name' => 'Chase',
            'last4' => 1234,
            'routing_number' => '1110000000',
            'verified' => false,
            'currency' => 'usd',
            'country' => 'US',
            'gateway' => MockGateway::ID,
            'gateway_id' => 'ba_test',
            'gateway_customer' => null,
            'gateway_setup_intent' => null,
            'merchant_account' => null,
            'chargeable' => true,
            'failure_reason' => null,
            'created_at' => self::$source->created_at,
            'updated_at' => self::$source->updated_at,
            'receipt_email' => null,
            'customer_id' => self::$customer->id,
            'account_holder_name' => null,
            'account_holder_type' => null,
            'type' => null,
        ];
    }

    public function editSource(): void
    {
        self::$source->verified = true; /* @phpstan-ignore-line */
    }

    public function testGetMethodDirectDebitGoCardless(): void
    {
        $bankAccount = new BankAccount();

        $bankAccount->currency = 'usd';
        $bankAccount->gateway = GoCardlessGateway::ID;
        $bankAccount->country = 'US';
        $this->assertEquals(PaymentMethod::DIRECT_DEBIT, $bankAccount->getMethod());

        $bankAccount->currency = 'gbp';
        $bankAccount->country = 'GB';
        $this->assertEquals(PaymentMethod::DIRECT_DEBIT, $bankAccount->getMethod());

        $bankAccount->currency = 'eur';
        $bankAccount->country = 'IT';
        $this->assertEquals(PaymentMethod::DIRECT_DEBIT, $bankAccount->getMethod());

        $bankAccount->currency = 'aud';
        $bankAccount->country = 'AU';
        $this->assertEquals(PaymentMethod::DIRECT_DEBIT, $bankAccount->getMethod());

        $bankAccount->country = 'SK';
        $this->assertEquals(PaymentMethod::DIRECT_DEBIT, $bankAccount->getMethod());
    }

    public function testGetMethodAch(): void
    {
        $bankAccount = new BankAccount();
        $bankAccount->country = 'US';
        $bankAccount->gateway = 'invoiced';
        $bankAccount->currency = 'usd';
        $this->assertEquals(PaymentMethod::ACH, $bankAccount->getMethod());
    }

    public function testGetMethodFlywire(): void
    {
        $bankAccount = new BankAccount();
        $bankAccount->country = 'US';
        $bankAccount->gateway = 'flywire';
        $bankAccount->currency = 'usd';
        $this->assertEquals(PaymentMethod::DIRECT_DEBIT, $bankAccount->getMethod());
    }

    public function testToString(): void
    {
        $bankAccount = new BankAccount();
        $bankAccount->last4 = '6789';
        $bankAccount->bank_name = 'Wells Fargo';

        $this->assertEquals('Wells Fargo *6789', $bankAccount->toString());

        $this->assertEquals('Wells Fargo *6789', $bankAccount->toString(true));
    }

    public function testNeedsVerification(): void
    {
        $bankAccount = new BankAccount();
        $this->assertFalse($bankAccount->needsVerification());

        $bankAccount->gateway = StripeGateway::ID;
        $this->assertTrue($bankAccount->needsVerification());

        $bankAccount->verified = true;
        $this->assertFalse($bankAccount->needsVerification());
    }
}
