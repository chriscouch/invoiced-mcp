<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Interfaces\OneTimeChargeInterface;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TestCredentialsInterface;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;

/**
 * This payment gateway replicates the Invoiced gateway without
 * performing any API calls. It should only be used in tests.
 */
class MockGateway implements PaymentGatewayInterface, OneTimeChargeInterface, PaymentSourceVaultInterface, RefundInterface, TestCredentialsInterface
{
    const ID = 'mock';

    public static function getId(): string
    {
        return self::ID;
    }

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
    }

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        return new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: uniqid(),
            method: PaymentMethod::CREDIT_CARD,
            status: ChargeValueObject::SUCCEEDED,
            merchantAccount: $account,
            source: null,
            description: $description,
        );
    }

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: uniqid(),
            status: RefundValueObject::SUCCEEDED,
        );
    }

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        if (array_value($parameters, 'ach')) {
            $verified = !array_value($parameters, 'unverified');

            return new BankAccountValueObject(
                customer: $customer,
                gateway: self::ID,
                gatewayId: uniqid(),
                chargeable: true,
                bankName: 'Test Bank',
                routingNumber: '110000000',
                last4: '1234',
                currency: 'usd',
                country: 'US',
                verified: $verified,
            );
        }

        return new CardValueObject(
            customer: $customer,
            gateway: self::ID,
            gatewayId: uniqid(),
            chargeable: true,
            brand: 'Visa',
            funding: 'unknown',
            last4: '1234',
            expMonth: 2,
            expYear: 2020,
        );
    }

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $invoice = $documents[0];
        if (property_exists($invoice->metadata, 'collection_fails')) {
            throw new ChargeException('Payment declined.');
        }

        return new ChargeValueObject(
            customer: $invoice->customer(),
            amount: $amount,
            gateway: self::ID,
            gatewayId: uniqid(),
            method: $source->getMethod(),
            status: ChargeValueObject::SUCCEEDED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
        );
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        // nothing to do
    }

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        // should always pass
    }
}
