<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Exceptions\TestGatewayCredentialsException;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Exceptions\VoidAlreadySettledException;
use App\PaymentProcessing\Exceptions\VoidException;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TestCredentialsInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Interfaces\VoidInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\HttpClientFactory;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

class NmiGateway extends AbstractLegacyGateway implements RefundInterface, VoidInterface, TestCredentialsInterface, TransactionStatusInterface
{
    const ID = 'nmi';

    private const API_URL = 'https://secure.networkmerchants.com/api/';
    private const API_TRANSACT = 'transact.php';
    private const API_QUERY = 'query.php';

    private const TRANSACTION_SALE = 'sale';
    private const TRANSACTION_DELETE_CUSTOMER = 'delete_customer';
    private const TRANSACTION_VOID = 'void';
    private const TRANSACTION_REFUND = 'refund';

    private const RESPONSE_APPROVED = 1;

    private const PAYMENT_ACH = 'check';

    private const ACCOUNT_HOLDER_BUSINESS = 'business';
    private const ACCOUNT_HOLDER_PERSONAL = 'personal';
    private const ACCOUNT_TYPE_CHECKING = 'checking';
    private const ACCOUNT_TYPE_SAVINGS = 'savings';

    private const ERROR_MESSAGES = [
        '100' => 'Transaction was approved.',
        '200' => 'Transaction was declined by processor.',
        '201' => 'Do not honor.',
        '202' => 'Insufficient funds.',
        '203' => 'Over limit.',
        '204' => 'Transaction not allowed.',
        '220' => 'Incorrect payment information.',
        '221' => 'No such card issuer.',
        '222' => 'No card number on file with issuer.',
        '223' => 'Expired card.',
        '224' => 'Invalid expiration date.',
        '225' => 'Invalid card security code.',
        '240' => 'Call issuer for further information.',
        '250' => 'Pick up card.',
        '251' => 'Lost card.',
        '252' => 'Stolen card.',
        '253' => 'Fraudulent card.',
        '260' => 'Declined with further instructions available. (See response text)',
        '261' => 'Declined-Stop all recurring payments.',
        '262' => 'Declined-Stop this recurring program.',
        '263' => 'Declined-Update cardholder data available.',
        '264' => 'Declined-Retry in a few days.',
        '300' => 'Transaction was rejected by gateway.',
        '400' => 'Transaction error returned by processor.',
        '410' => 'Invalid merchant configuration.',
        '411' => 'Merchant account is inactive.',
        '420' => 'Communication error.',
        '421' => 'Communication error with issuer.',
        '430' => 'Duplicate transaction at processor.',
        '440' => 'Processor format error.',
        '441' => 'Invalid transaction information.',
        '460' => 'Processor feature not available.',
        '461' => 'Unsupported card type.',
    ];

    private const MASKED_REQUEST_PARAMETERS = [
        'password',
        'ccnumber',
        'cvv',
        'checkaccount',
    ];

    private array $voidResult;

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->username)) {
            throw new InvalidGatewayConfigurationException('Missing NMI username!');
        }

        if (!isset($gatewayConfiguration->credentials->password)) {
            throw new InvalidGatewayConfigurationException('Missing NMI password!');
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

        // set up the transaction
        $sale = $this->buildSale($source, $amount, $description, $documents, $parameters);
        $sale['customer_vault_id'] = $source->gateway_id;
        $gatewayConfiguration = $account->toGatewayConfiguration();

        // perform the call on NMI
        try {
            $response = $this->performTransactRequest($gatewayConfiguration, $sale);
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            throw new ChargeException($this->buildErrorMessage($result));
        } catch (GuzzleException) {
            throw new ChargeException('An unknown error has occurred when communicating with the NMI gateway.');
        }

        // parse the response
        $result = $this->parseResponse($response);

        if (self::RESPONSE_APPROVED == $result['response']) {
            return $this->buildCharge($result, $source, $amount, $description);
        }

        $failedCharge = $this->buildFailedCharge($result, $source, $amount, $description);

        throw new ChargeException($this->buildErrorMessage($result), $failedCharge);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        $params = [
            'customer_vault' => self::TRANSACTION_DELETE_CUSTOMER,
            'customer_vault_id' => $source->gateway_id,
        ];

        // perform the call on NMI
        try {
            $response = $this->performTransactRequest($account->toGatewayConfiguration(), $params);
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            throw new PaymentSourceException($this->buildErrorMessage($result));
        } catch (GuzzleException) {
            throw new PaymentSourceException('An unknown error has occurred when communicating with the NMI gateway.');
        }

        // parse the response
        $result = $this->parseResponse($response);

        if (self::RESPONSE_APPROVED != $result['response']) {
            throw new PaymentSourceException($this->buildErrorMessage($result));
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
            $this->void($merchantAccount, $amount);

            return $this->buildRefund($this->voidResult, $amount);
        } catch (VoidException $e) {
            // do nothing
        }

        return $this->credit($merchantAccount, $chargeId, $amount);
    }

    public function void(MerchantAccount $merchantAccount, string $chargeId): void
    {
        $void = [
            'type' => self::TRANSACTION_VOID,
            'transactionid' => $chargeId,
        ];

        // perform the call on NMI
        try {
            $response = $this->performTransactRequest($merchantAccount->toGatewayConfiguration(), $void);
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            throw new VoidException($this->buildErrorMessage($result));
        } catch (GuzzleException) {
            throw new VoidException('An unknown error has occurred when communicating with the NMI gateway.');
        }

        // parse the response
        $this->voidResult = $this->parseResponse($response);

        if (self::RESPONSE_APPROVED == $this->voidResult['response']) {
            return;
        }

        // handle already settled error
        if (false !== strpos($this->voidResult['responsetext'], 'Only transactions pending settlement can be voided')) {
            throw new VoidAlreadySettledException('Already settled');
        }

        throw new VoidException($this->buildErrorMessage($this->voidResult));
    }

    //
    // Transaction Status
    //

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        $chargeId = $charge->gateway_id;
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $params = [
            'transaction_id' => $chargeId,
        ];

        try {
            $response = $this->performQueryRequest($gatewayConfiguration, $params);
        } catch (GuzzleException) {
            throw new TransactionStatusException('An unknown error has occurred when communicating with the NMI gateway.');
        }

        /** @var SimpleXMLElement $xml */
        $xml = simplexml_load_string($response->getBody());

        $condition = (string) $xml->transaction->condition;

        $status = ChargeValueObject::PENDING;

        if (in_array($condition, ['failed', 'canceled', 'unknown', 'abandoned'])) {
            $status = ChargeValueObject::FAILED;
        } elseif (in_array($condition, ['complete'])) {
            $status = ChargeValueObject::SUCCEEDED;
        } elseif (in_array($condition, ['pending', 'in_progress', 'pendingsettlement'])) {
            $status = ChargeValueObject::PENDING;
        }

        return [$status, $condition];
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        $params = [
            'transaction_id' => uniqid(),
        ];

        try {
            $response = $this->performQueryRequest($gatewayConfiguration, $params);
        } catch (GuzzleException) {
            throw new TestGatewayCredentialsException('An unknown error has occurred when communicating with the NMI gateway.');
        }

        /** @var SimpleXMLElement $xml */
        $xml = simplexml_load_string($response->getBody());

        if (false !== strpos($xml->error_response, 'Invalid Username/Password')) {
            throw new TestGatewayCredentialsException('Invalid Username/Password');
        }
    }

    //
    // Helpers
    //

    /**
     * Builds an HTTP client for talking to NMI.
     */
    private function getClient(): Client
    {
        return HttpClientFactory::make($this->gatewayLogger, [
            'base_uri' => self::API_URL,
        ]);
    }

    /**
     * @throws GuzzleException
     */
    private function performTransactRequest(PaymentGatewayConfiguration $gatewayConfiguration, array $params): ResponseInterface
    {
        $params['username'] = $gatewayConfiguration->credentials->username;
        $params['password'] = $gatewayConfiguration->credentials->password;

        $this->gatewayLogger->logFormDataRequest($params, self::MASKED_REQUEST_PARAMETERS);

        return $this->getClient()->request('POST', self::API_TRANSACT, ['form_params' => $params]);
    }

    /**
     * @throws GuzzleException
     */
    private function performQueryRequest(PaymentGatewayConfiguration $gatewayConfiguration, array $params): ResponseInterface
    {
        $params['username'] = $gatewayConfiguration->credentials->username;
        $params['password'] = $gatewayConfiguration->credentials->password;

        $this->gatewayLogger->logFormDataRequest($params, self::MASKED_REQUEST_PARAMETERS);

        return $this->getClient()->request('POST', self::API_QUERY, ['form_params' => $params]);
    }

    /**
     * Parses a response from the NMI gateway.
     */
    private function parseResponse(ResponseInterface $response): array
    {
        parse_str($response->getBody(), $result);

        return $result;
    }

    /**
     * Builds an error message from an NMI transaction response.
     */
    private function buildErrorMessage(array $result): string
    {
        $code = $result['response_code'];
        if (isset(self::ERROR_MESSAGES[$code])) {
            return self::ERROR_MESSAGES[$code];
        }

        if ($result['responsetext']) {
            return $result['responsetext'];
        }

        return 'An unknown error has occurred.';
    }

    /**
     * Builds a Refund object from an NMI transaction response.
     */
    private function buildRefund(array $result, Money $amount): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result['transactionid'],
            status: RefundValueObject::SUCCEEDED,
        );
    }

    /**
     * Performs a refund on NMI.
     */
    private function credit(MerchantAccount $account, string $chargeId, Money $amount): RefundValueObject
    {
        $credit = [
            'type' => self::TRANSACTION_REFUND,
            'transactionid' => $chargeId,
            'amount' => $amount->toDecimal(),
        ];

        // perform the call on NMI
        try {
            $response = $this->performTransactRequest($account->toGatewayConfiguration(), $credit);
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            throw new RefundException($this->buildErrorMessage($result));
        } catch (GuzzleException) {
            throw new RefundException('An unknown error has occurred when communicating with the NMI gateway.');
        }

        // parse the response
        $result = $this->parseResponse($response);

        if (self::RESPONSE_APPROVED == $result['response']) {
            return $this->buildRefund($result, $amount);
        }

        throw new RefundException($this->buildErrorMessage($result));
    }

    /**
     * Builds an NMI sale transaction.
     *
     * @param ReceivableDocument[] $documents
     */
    private function buildSale(PaymentSource $source, Money $amount, string $description, array $documents, array $parameters): array
    {
        $params = [
            'type' => self::TRANSACTION_SALE,
            'currency' => $amount->currency,
            'amount' => $amount->toDecimal(),
        ];

        // set the transaction metadata
        $customer = $source->customer;
        if ($email = GatewayHelper::getEmail($customer, $parameters)) {
            $params['email'] = $email;
        }
        $params['order_description'] = $description;
        $params['merchant_defined_field_1'] = (string) $customer->id;
        if (count($documents) > 0) {
            $params['orderid'] = (string) $documents[0]->id;
        }

        return $params;
    }

    /**
     * Builds a charge object from an NMI transaction response.
     */
    private function buildCharge(array $result, PaymentSource $source, Money $amount, string $description): ChargeValueObject
    {
        // WARNING NMI can do partial authorizations however, the response
        // does not include the amount that was actually approved. This could be a bug.

        $status = ChargeValueObject::SUCCEEDED;
        if ($source instanceof BankAccount) {
            $status = ChargeValueObject::PENDING;
        }

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result['transactionid'],
            method: '',
            status: $status,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $result['responsetext'],
        );
    }

    /**
     * Builds a failed charge object from an NMI transaction response.
     */
    private function buildFailedCharge(array $result, PaymentSource $source, Money $amount, string $description): ChargeValueObject
    {
        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result['transactionid'],
            method: '',
            status: ChargeValueObject::FAILED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $this->buildErrorMessage($result),
        );
    }

    /**
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function chargeBankAccount(BankAccount $bankAccount, MerchantAccount $account, Money $amount, array $parameters, array $documents, string $description): ChargeValueObject
    {
        // set up the transaction
        $sale = $this->buildSale($bankAccount, $amount, $description, $documents, $parameters);

        // add payment info
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $sale = $this->withBankAccount($gatewayConfiguration, $sale, $bankAccount);

        // perform the call on NMI
        try {
            $response = $this->performTransactRequest($gatewayConfiguration, $sale);
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            throw new ChargeException($this->buildErrorMessage($result));
        } catch (GuzzleException) {
            throw new ChargeException('An unknown error has occurred when communicating with the NMI gateway.');
        }

        // parse the response
        $result = $this->parseResponse($response);

        if (self::RESPONSE_APPROVED == $result['response']) {
            return $this->buildCharge($result, $bankAccount, $amount, $description);
        }

        $failedCharge = $this->buildFailedCharge($result, $bankAccount, $amount, $description);

        throw new ChargeException($this->buildErrorMessage($result), $failedCharge);
    }

    /**
     * Adds a bank account to an NMI transaction.
     */
    private function withBankAccount(PaymentGatewayConfiguration $gatewayConfiguration, array $transaction, BankAccount $bankAccount): array
    {
        $transaction['payment'] = self::PAYMENT_ACH;
        $transaction['checkaccount'] = $bankAccount->account_number;
        $transaction['checkaba'] = $bankAccount->routing_number;
        $transaction['checkname'] = $bankAccount->account_holder_name;

        $transaction['account_holder_type'] = self::ACCOUNT_HOLDER_BUSINESS;
        $transaction['sec_code'] = GatewayHelper::secCodeByOwnerType($gatewayConfiguration, $bankAccount);
        if (BankAccountValueObject::TYPE_INDIVIDUAL == $bankAccount->account_holder_type) {
            $transaction['account_holder_type'] = self::ACCOUNT_HOLDER_PERSONAL;
        }

        $transaction['account_type'] = self::ACCOUNT_TYPE_CHECKING;
        if (BankAccountValueObject::TYPE_SAVINGS == $bankAccount->type) {
            $transaction['account_type'] = self::ACCOUNT_TYPE_SAVINGS;
        }

        return $transaction;
    }
}
