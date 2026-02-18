<?php

namespace App\Tests\PaymentProcessing\Operations;

use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\VerifyBankAccountInterface;
use App\PaymentProcessing\Operations\VerifyBankAccount;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\Tests\AppTestCase;

class VerifyBankAccountTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasBankAccount();
    }

    public function testVerify(): void
    {
        // mock gateway operations
        $gateway = \Mockery::mock(PaymentGatewayInterface::class.','.VerifyBankAccountInterface::class);
        $gateway->shouldReceive('validateConfiguration');

        $originalBankAccount = self::$bankAccount;
        $reconciledBankAccount = new BankAccountValueObject(
            customer: self::$customer,
            gateway: $gateway,
            chargeable: true,
            bankName: 'Test Bank',
            routingNumber: '110000000',
            last4: '9999',
            currency: 'USD',
            country: 'US',
            verified: true,
        );

        $gateway->shouldReceive('verifyBankAccount')
            ->once()
            ->andReturn($reconciledBankAccount);

        $gatewayFactory = \Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')->andReturn($gateway);

        $verifyBankAccount = new VerifyBankAccount($gatewayFactory);

        $verifyBankAccount->verify($originalBankAccount, 45, 32);

        $this->assertTrue($originalBankAccount->verified);
    }
}
