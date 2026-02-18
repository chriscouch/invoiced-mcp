<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Exception\ModelException;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\VerifyBankAccountException;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Interfaces\VerifyBankAccountInterface;
use App\PaymentProcessing\Interfaces\VoidInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use App\Tokenization\Traits\InvoicedTokenizationTrait;

class TestGateway extends AbstractGateway implements RefundInterface, VoidInterface, TransactionStatusInterface, VerifyBankAccountInterface
{
    use InvoicedTokenizationTrait;

    const ID = 'test';

    /**
     * Map of charge ID prefixes to return based on payment information.
     */
    private const CHARGE_IDS = [
        '2227:123123123' => 'fail',
    ];

    /**
     * Test Cards, Key is card number and value is status.
     */
    private const CARD_STATUSES = [
        '0069:visa' => ChargeValueObject::FAILED,
        '0127:visa' => ChargeValueObject::FAILED,
    ];

    /**
     * Test Bank Accounts. Keys are account numbers and values are status' they return.
     */
    private const BANK_ACCOUNT_STATUSES = [
        '3456:123123123' => ChargeValueObject::FAILED,
        '3893:123123123' => ChargeValueObject::PENDING,
        '2227:123123123' => ChargeValueObject::PENDING,
        '1116:123123123' => ChargeValueObject::FAILED,
    ];

    private const TEST_ACH_ROUTING_NUMBERS = [
        '110000000',
        '123123123',
    ];

    private const TEST_BANK_ACCOUNT_NUMBERS = [
        'AT611904300235473201',
        'AT861904300235473202',
        'BE62510007547061',
        'BE68539007547034',
        'DK5000400440116243',
        'DK8003450003179681',
        'FR1420041010050500013M02606',
        'FR8420041010050500013M02607',
        'IE29AIBK93115212345678',
        'IE02AIBK93115212345679',
        'IT40S0542811101000000123456',
        'IT60X0542811101000000123456',
        'LU280019400644750000',
        'LU980019400644750001',
        'NL39RABO0300065264',
        'NL91ABNA0417164300',
        'PT50000201231234567890154',
        'PT23000201231234567890155',
        'ES0700120345030000067890',
        'ES9121000418450200051332',
    ];

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
    }

    //
    // One-Time Charges
    //

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        // Handle ACH charges
        $paymentMethod = $parameters['payment_method'] ?? '';
        if ('ach' == $paymentMethod) {
            return $this->chargeBankAccount($customer, $account, $amount, $parameters, $description);
        }

        // Other payment types fall back to the payment server
        return parent::charge($customer, $account, $amount, $parameters, $description, $documents);
    }

    //
    // Payment Sources
    //

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        //try Adyen tokenization first
        if (isset($parameters['invoiced_token'])) {
            try {
                return $this->vaultInvoicedSource($parameters['invoiced_token'], $customer, $account);
            } catch (ModelException){
                //do nothing, continue to legacy tokenization
            }
        }

        // Handle ACH payment information
        $paymentMethod = $parameters['payment_method'] ?? '';
        if ('ach' == $paymentMethod) {
            try {
                return GatewayHelper::makeAchBankAccount($this->routingNumberLookup, $customer, $account, $parameters, true);
            } catch (InvalidBankAccountException $e) {
                throw new PaymentSourceException($e->getMessage(), $e->getCode(), $e);
            }
        }

        // Other payment types fall back to the payment server
        return parent::vaultSource($customer, $account, $parameters);
    }

    public function verifyBankAccount(MerchantAccount $merchantAccount, BankAccount $bankAccount, int $amount1, int $amount2): void
    {
        $given_amounts = [$amount1, $amount2];
        if (in_array(32, $given_amounts) && in_array(45, $given_amounts)) {
            return;
        }

        throw new VerifyBankAccountException('Your bank account could not be successfully verified.');
    }

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $key = '';
        $status = ChargeValueObject::SUCCEEDED;
        if ($source instanceof BankAccount) {
            $key = $source->last4.':'.$source->routing_number;
            $status = array_key_exists($key, self::BANK_ACCOUNT_STATUSES) ? self::BANK_ACCOUNT_STATUSES[$key] : $status;
        }

        if ($source instanceof Card) {
            $key = $source->last4.':'.strtolower($source->brand);
            $status = array_key_exists($key, self::CARD_STATUSES) ? self::CARD_STATUSES[$key] : $status;
        }

        if (ChargeValueObject::FAILED == $status) {
            throw new ChargeException('Charge declined', $this->buildFailedCharge($amount, $source, $description));
        }

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $this->getChargeId($key),
            method: $source->getMethod(),
            status: $status,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
        );
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        // nothing to do
    }

    //
    // Refunds
    //

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: uniqid(),
            status: RefundValueObject::SUCCEEDED,
        );
    }

    public function void(MerchantAccount $merchantAccount, string $chargeId): void
    {
        // do nothing
    }

    //
    // Transaction Status
    //

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        $chargeId = $charge->gateway_id;
        // check the status here
        $status = ChargeValueObject::SUCCEEDED;
        $message = null;

        if (false !== strpos($chargeId, 'fail')) {
            $status = ChargeValueObject::FAILED;
            $message = 'Declined Payment';
        }

        return [$status, $message];
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        // should always pass
    }

    //
    // Helpers
    //

    /**
     * Generates the ID for a charge based on the payment information used.
     */
    private function getChargeId(string $key): string
    {
        $id = uniqid();
        if (isset(self::CHARGE_IDS[$key])) {
            $id .= '_'.self::CHARGE_IDS[$key];
        }

        return $id;
    }

    /**
     * Builds a failed charge object.
     */
    private function buildFailedCharge(Money $amount, PaymentSource $source, string $description): ChargeValueObject
    {
        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: uniqid(),
            method: $source->getMethod(),
            status: ChargeValueObject::FAILED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: 'Declined charge',
        );
    }

    private function chargeBankAccount(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description): ChargeValueObject
    {
        try {
            $bankAccountValueObject = GatewayHelper::makeAchBankAccount($this->routingNumberLookup, $customer, $account, $parameters, false);

            if (!$this->isTestBankAccount($bankAccountValueObject)) {
                throw new ChargeException('Your request was against the test gateway, but used a non test (live) bank account. For a list of valid test bank accounts, visit: https://invoiced.com/docs/dev/testing.');
            }

            /** @var BankAccount $bankAccountModel */
            $bankAccountModel = $this->sourceReconciler->reconcile($bankAccountValueObject);
        } catch (ReconciliationException|InvalidBankAccountException $e) {
            throw new ChargeException($e->getMessage());
        }

        $key = $bankAccountValueObject->last4.':'.$bankAccountValueObject->routingNumber;
        $status = array_key_exists($key, self::BANK_ACCOUNT_STATUSES) ? self::BANK_ACCOUNT_STATUSES[$key] : ChargeValueObject::SUCCEEDED;

        if (ChargeValueObject::FAILED == $status) {
            throw new ChargeException('Charge declined', $this->buildFailedCharge($amount, $bankAccountModel, $description));
        }

        return new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $this->getChargeId($key),
            method: PaymentMethod::ACH,
            status: $status,
            merchantAccount: $account,
            source: $bankAccountModel,
            description: $description,
        );
    }

    /**
     * Checks if a card is a known test card (versus a live account).
     */
    public function isTestBankAccount(BankAccountValueObject $bankAccount): bool
    {
        if ($routingNumber = $bankAccount->routingNumber) {
            return in_array($routingNumber, self::TEST_ACH_ROUTING_NUMBERS);
        }

        return in_array($bankAccount->accountNumber, self::TEST_BANK_ACCOUNT_NUMBERS);
    }
}
