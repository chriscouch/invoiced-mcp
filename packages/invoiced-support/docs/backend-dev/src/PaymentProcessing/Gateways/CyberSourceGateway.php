<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\CyberSource\CyberSourceClient;
use App\Integrations\CyberSource\CyberSourceDeclinedException;
use App\Integrations\CyberSource\CyberSourceException;
use App\Integrations\CyberSource\CyberSourceInvalidFieldException;
use App\Integrations\CyberSource\CyberSourceMissingFieldException;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Exceptions\TestGatewayCredentialsException;
use App\PaymentProcessing\Exceptions\VoidAlreadySettledException;
use App\PaymentProcessing\Exceptions\VoidException;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TestCredentialsInterface;
use App\PaymentProcessing\Interfaces\VoidInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use stdClass;

class CyberSourceGateway extends AbstractLegacyGateway implements RefundInterface, VoidInterface, TestCredentialsInterface
{
    const ID = 'cybersource';

    /**
     * Failure reason codes we might receive when
     * running a void that indicate we should try
     * to initiate a credit instead.
     */
    private const VOID_ALREADY_SETTLED_CODES = [
        '246', // The requested transaction has already been voided or batched, or it does not support void, or it does not exist.
    ];

    private object $voidResponse;

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->merchant_id)) {
            throw new InvalidGatewayConfigurationException('Missing CyberSource merchant ID!');
        }

        if (!isset($gatewayConfiguration->credentials->transaction_key)) {
            throw new InvalidGatewayConfigurationException('Missing CyberSource transaction key!');
        }
    }

    //
    // One-Time Charges
    //

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $paymentMethod = $parameters['payment_method'] ?? '';
        if ('ach' == $paymentMethod) {
            try {
                $bankAccountValueObject = GatewayHelper::makeAchBankAccount($this->routingNumberLookup, $customer, $account, $parameters, false);
                /** @var BankAccount $bankAccountModel */
                $bankAccountModel = $this->sourceReconciler->reconcile($bankAccountValueObject);
            } catch (ReconciliationException|InvalidBankAccountException $e) {
                throw new ChargeException($e->getMessage());
            }

            return $this->chargeBankAccount($bankAccountModel, $account, $amount, $parameters, $documents, $description);
        }

        // Other payment types fall back to the payment server
        return parent::charge($customer, $account, $amount, $parameters, $description, $documents);
    }

    //
    // Payment Sources
    //

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
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

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        // Charge a bank account vaulted in our database instead of on the gateway
        $account = $source->getMerchantAccount();
        if ($source instanceof BankAccount && $source->account_number) {
            return $this->chargeBankAccount($source, $account, $amount, $parameters, $documents, $description);
        }

        $gatewayConfiguration = $account->toGatewayConfiguration();
        $client = $this->getClient($gatewayConfiguration);

        $this->addSource($client, $source, $description, $documents);

        try {
            if ($source instanceof BankAccount) {
                $response = $client->chargeStoredBankAccount($amount->currency, $amount->toDecimal());
            } else {
                $response = $client->chargeStoredCard($amount->currency, $amount->toDecimal());
            }
        } catch (CyberSourceException $e) {
            $failedCharge = null;
            if ($e instanceof CyberSourceDeclinedException) {
                // build a failed charge
                $failedCharge = $this->buildCharge($client->response, $source, $amount, $description);
            }

            throw new ChargeException($e->getMessage(), $failedCharge);
        }

        return $this->buildCharge($response, $source, $amount, $description);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        $client = $this->getClient($account->toGatewayConfiguration());

        try {
            $client->deleteProfile((string) $source->gateway_id);
        } catch (CyberSourceException $e) {
            throw new PaymentSourceException($e->getMessage());
        }
    }

    //
    // Refunds
    //

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        // First we are going to attempt to void the transaction.
        // Once the transaction has been settled then any attempts to
        // void the transaction will fail. If a void does not work then we
        // must issue a credit. Voids are preferred because they are
        // free and fast, whereas a credit might cost money and take
        // several business days to appear for the customer.

        try {
            $this->void($merchantAccount, $chargeId);

            return $this->buildRefund($this->voidResponse, $amount);
        } catch (VoidException) {
            // do nothing
        }

        return $this->credit($merchantAccount, $chargeId, $amount);
    }

    public function void(MerchantAccount $merchantAccount, string $chargeId): void
    {
        $client = $this->getClient($merchantAccount->toGatewayConfiguration());

        try {
            $this->voidResponse = $client->void($chargeId);
        } catch (CyberSourceException $e) {
            // The CyberSource client will throw either an error rejection or a missing field error.
            // This seems to vary depending on the transaction circumstances. Both mean that the transaction
            // has already been settled
            if ($e instanceof CyberSourceMissingFieldException || in_array($e->getCode(), self::VOID_ALREADY_SETTLED_CODES)) {
                throw new VoidAlreadySettledException('Already settled');
            }

            throw new VoidException('Could not void transaction: '.$e->getMessage());
        }
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        $client = $this->getClient($gatewayConfiguration);

        // we test the credentials by running a void that is known to fail
        // based on the failure response we can determine if the credentials are valid

        try {
            $client->void('-1');
        } catch (CyberSourceException $e) {
            if ($e instanceof CyberSourceInvalidFieldException) {
                // we expect the void to be rejected since it references a non-existent transaction
                // this means the credentials are valid
            } else {
                throw new TestGatewayCredentialsException(trim($e->getMessage()));
            }
        }
    }

    //
    // Helpers
    //

    /**
     * Gets the CyberSource HTTP client.
     */
    public function getClient(PaymentGatewayConfiguration $gatewayConfiguration): CyberSourceClient
    {
        $testMode = isset($gatewayConfiguration->credentials->test_mode) && $gatewayConfiguration->credentials->test_mode;

        $client = new CyberSourceClient($gatewayConfiguration->credentials->merchant_id, $gatewayConfiguration->credentials->transaction_key, $testMode);
        $client->setGatewayLogger($this->gatewayLogger);

        return $client;
    }

    /**
     * Builds a Refund object from a CyberSource transaction response.
     */
    private function buildRefund(object $result, Money $amount): RefundValueObject
    {
        if (isset($result->voidReply)) {
            $amount = Money::fromDecimal($result->voidReply->currency, $result->voidReply->amount);
        } elseif (isset($result->ccCreditReply)) {
            $amount = Money::fromDecimal($amount->currency, $result->ccCreditReply->amount);
        }

        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result->requestID,
            status: RefundValueObject::SUCCEEDED,
        );
    }

    /**
     * Performs a credit on CyberSource.
     *
     * @throws RefundException
     */
    private function credit(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        $client = $this->getClient($merchantAccount->toGatewayConfiguration());

        try {
            $response = $client->credit($chargeId, $amount->currency, $amount->toDecimal());
        } catch (CyberSourceException $e) {
            throw new RefundException($e->getMessage());
        }

        return $this->buildRefund($response, $amount);
    }

    /**
     * @param ReceivableDocument[] $documents
     */
    private function addSource(CyberSourceClient $client, PaymentSource $source, string $description, array $documents): void
    {
        $client->storedProfile((string) $source->gateway_id);

        // pass in extra transaction metadata
        $merchantDefinedData = [
            (string) $source->customer_id,
            $source->customer->number,
        ];
        $merchantDefinedData[] = $description;
        $client->merchantDefinedData($merchantDefinedData);

        if (count($documents) > 0) {
            $client->setReferenceCode((string) $documents[0]->id);
        }
    }

    /**
     * Builds a charge object from a CyberSource transaction response.
     */
    private function buildCharge(stdClass $result, PaymentSource $source, Money $amount, string $description): ChargeValueObject
    {
        $total = $amount;
        if (isset($result->ccAuthReply->amount)) {
            $total = Money::fromDecimal($amount->currency, (float) $result->ccAuthReply->amount);
        } elseif (isset($result->ecDebitReply->amount)) {
            $total = Money::fromDecimal($amount->currency, (float) $result->ecDebitReply->amount);
        }

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $total,
            gateway: self::ID,
            gatewayId: $result->requestID,
            method: '',
            status: CyberSourceClient::DECISION_ACCEPT == $result->decision ? ChargeValueObject::SUCCEEDED : ChargeValueObject::FAILED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: CyberSourceClient::$resultCodes[$result->reasonCode],
        );
    }

    /**
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function chargeBankAccount(BankAccount $bankAccount, MerchantAccount $account, Money $amount, array $parameters, array $documents, string $description): ChargeValueObject
    {
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $client = $this->getClient($gatewayConfiguration);

        $this->addBankAccount($gatewayConfiguration, $client, $bankAccount, $parameters, $documents, $description);

        try {
            $response = $client->chargeBankAccount($amount->currency, $amount->toDecimal());
        } catch (CyberSourceException $e) {
            $failedCharge = null;
            if ($e instanceof CyberSourceDeclinedException) {
                // build a failed charge
                $failedCharge = $this->buildCharge($client->response, $bankAccount, $amount, $description);
            }

            throw new ChargeException($e->getMessage(), $failedCharge);
        }

        return $this->buildCharge($response, $bankAccount, $amount, $description);
    }

    /**
     * @param ReceivableDocument[] $documents
     */
    private function addBankAccount(PaymentGatewayConfiguration $gatewayConfiguration, CyberSourceClient $client, BankAccount $bankAccount, array $parameters, array $documents, string $description): void
    {
        $accountType = BankAccountValueObject::TYPE_SAVINGS == $bankAccount->account_holder_type ? CyberSourceClient::ACH_TYPE_SAVINGS : CyberSourceClient::ACH_TYPE_CHECKING;
        $accountParams = [
            'accountNumber' => $bankAccount->account_number,
            'accountType' => $accountType,
            'bankTransitNumber' => $bankAccount->routing_number,
            'secCode' => GatewayHelper::secCodeWeb($gatewayConfiguration),
        ];

        // billing address
        $billTo = [
            'street1' => '900 Metro Center Blvd.',
            'city' => 'Foster City',
            'state' => 'CA',
            'postalCode' => '94404',
            'country' => $bankAccount->country,
            'phoneNumber' => '650-432-7350',
        ];

        if (BankAccountValueObject::TYPE_COMPANY == $bankAccount->account_holder_type) {
            // CyberSource recommends this format for corporate accounts
            $billTo['firstName'] = 'NA';
            $billTo['lastName'] = $this->sanitizeName((string) $bankAccount->account_holder_name);
        } else {
            $billTo = array_merge($billTo, $this->parseName((string) $bankAccount->account_holder_name));
        }

        // pass in extra transaction metadata
        $merchantDefinedData = [];
        $merchantDefinedData[] = $bankAccount->customer_id;
        $merchantDefinedData[] = $bankAccount->customer->number;
        if (count($documents) > 0) {
            $client->setReferenceCode((string) $documents[0]->id);
        }
        $merchantDefinedData[] = $description;
        if (isset($parameters['receipt_email'])) {
            $billTo['email'] = $parameters['receipt_email'];
        }

        $client->bankAccount($accountParams);
        $client->billTo($billTo);
        $client->merchantDefinedData($merchantDefinedData);
    }

    private function sanitizeName(string $name): string
    {
        $name = (string) preg_replace("/[^a-zA-Z0-9-_\s]/", '', $name);

        // replace multiply spaces resulted after first strip
        return trim((string) preg_replace("/\s+/", ' ', $name));
    }

    private function parseName(string $name): array
    {
        $names = explode(' ', $this->sanitizeName($name), 2);
        if (1 == count($names)) {
            return [
                'firstName' => 'NA',
                'lastName' => trim($names[0]),
            ];
        }

        return [
            'firstName' => trim($names[0]),
            'lastName' => trim($names[1]),
        ];
    }
}
