<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\RandomString;
use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Interfaces\OneTimeChargeInterface;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\RoutingNumberLookup;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\SourceValueObject;

/**
 * This gateway interfaces with ACH bank accounts for the purpose
 * of generate NACHA files downstream that are sent to a bank.
 */
class NachaGateway implements PaymentGatewayInterface, OneTimeChargeInterface, PaymentSourceVaultInterface, TransactionStatusInterface
{
    const ID = 'nacha';

    public static function getId(): string
    {
        return self::ID;
    }

    public function __construct(
        private PaymentSourceReconciler $paymentSourceReconciler,
        private RoutingNumberLookup $routingNumberLookup,
    ) {
    }

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
    }

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $bankAccount = GatewayHelper::makeAchBankAccount($this->routingNumberLookup, $customer, $account, $parameters, false);
        $source = $this->paymentSourceReconciler->reconcile($bankAccount);

        return new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $this->makeId(),
            method: PaymentMethod::ACH,
            status: ChargeValueObject::PENDING,
            merchantAccount: $account,
            source: $source,
            description: $description,
        );
    }

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        // We store ACH bank accounts in our database.
        try {
            return GatewayHelper::makeAchBankAccount($this->routingNumberLookup, $customer, $account, $parameters, true);
        } catch (InvalidBankAccountException $e) {
            throw new PaymentSourceException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $this->makeId(),
            method: PaymentMethod::CREDIT_CARD,
            status: ChargeValueObject::PENDING,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
        );
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        // nothing to do
    }

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        return [ChargeValueObject::PENDING, null];
    }

    private function makeId(): string
    {
        return RandomString::generate(10, 'abcdefghijklmnopqrstuvwxyz1234567890');
    }
}
