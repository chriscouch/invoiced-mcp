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
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class LawPayGateway extends AbstractLegacyGateway implements RefundInterface, VoidInterface, TestCredentialsInterface, TransactionStatusInterface
{
    const ID = 'lawpay';

    private const API_URL = 'https://api.chargeio.com/v1/';

    private const ERROR_NOT_VALID_FOR_TRANSACTION_STATUS = 'not_valid_for_transaction_status';

    private const CHARGE_STATUSES = [
        'PENDING' => ChargeValueObject::PENDING,
        'AUTHORIZED' => ChargeValueObject::SUCCEEDED,
        'COMPLETED' => ChargeValueObject::SUCCEEDED,
        'VOIDED' => ChargeValueObject::SUCCEEDED,
        'FAILED' => ChargeValueObject::FAILED,
    ];

    private const MASKED_REQUEST_PARAMETERS = [
        'number',
        'cvv',
        'account_number',
    ];

    private const TYPE_BANK_ACCOUNT = 'bank';

    private const ACH_TYPE_SAVINGS = 'SAVINGS';
    private const ACH_TYPE_CHECKING = 'CHECKING';

    private object $voidResult;

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->account_id)) {
            throw new InvalidGatewayConfigurationException('Missing LawPay account ID!');
        }

        if (!isset($gatewayConfiguration->credentials->secret_key)) {
            throw new InvalidGatewayConfigurationException('Missing LawPay secret key!');
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

            return $this->chargeBankAccount($bankAccountModel, $account, $amount, $documents, $description);
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
            return $this->chargeBankAccount($source, $account, $amount, $documents, $description);
        }

        // set up the charge
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $chargeParams = $this->buildLawPayCharge($gatewayConfiguration, $source, $amount, $description, $documents);
        $chargeParams['method'] = $source->gateway_id;

        // perform the charge on LawPay
        try {
            $response = $this->performPostRequest($gatewayConfiguration, 'charges', $chargeParams);
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            // build a failed charge if possible
            $charge = $this->getFailedCharge($result, $source, $gatewayConfiguration, $description);

            throw new ChargeException($this->buildErrorMessage($result), $charge);
        } catch (GuzzleException) {
            throw new ChargeException('An unknown error has occurred when communicating with LawPay');
        }

        // parse the response
        $result = $this->parseResponse($response);

        return $this->buildCharge($result, $source, $description);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        if ($source instanceof BankAccount) {
            $endpoint = 'banks/'.$source->gateway_id;
        } else {
            $endpoint = 'cards/'.$source->gateway_id;
        }

        // perform the call on LawPay
        try {
            $this->performDeleteRequest($account->toGatewayConfiguration(), $endpoint);
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            throw new PaymentSourceException($this->buildErrorMessage($result));
        } catch (GuzzleException) {
            throw new PaymentSourceException('An unknown error has occurred when communicating with LawPay');
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

            return $this->buildRefund($this->voidResult, $amount);
        } catch (VoidException) {
            // do nothing
        }

        return $this->performRefund($merchantAccount, $chargeId, $amount);
    }

    public function void(MerchantAccount $merchantAccount, string $chargeId): void
    {
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();

        // perform the call on LawPay
        try {
            $response = $this->performPostRequest($gatewayConfiguration, 'transactions/'.$chargeId.'/void');
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            // has the transaction already been settled?
            // throw a special exception to handle this state
            foreach ($result->messages as $part) {
                if (self::ERROR_NOT_VALID_FOR_TRANSACTION_STATUS == $part->code) {
                    throw new VoidAlreadySettledException('Already settled');
                }
            }

            throw new VoidException($this->buildErrorMessage($result));
        } catch (GuzzleException $e) {
            throw new VoidException('An unknown error has occurred when communicating with LawPay');
        }

        // parse the response
        $this->voidResult = $this->parseResponse($response);
    }

    //
    // Transaction Status
    //

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        $chargeId = $charge->gateway_id;
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();

        try {
            $response = $this->retrieveCharge($chargeId, $gatewayConfiguration);
        } catch (Exception $e) {
            throw new TransactionStatusException($e->getMessage());
        }

        // parse the response
        $result = $this->parseResponse($response);

        return $this->buildTransactionStatus($result);
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        try {
            $response = $this->performGetRequest($gatewayConfiguration, 'merchant');
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            throw new TestGatewayCredentialsException($this->buildErrorMessage($result));
        } catch (GuzzleException) {
            throw new TestGatewayCredentialsException('An unknown error has occurred when communicating with LawPay');
        }

        $this->parseResponse($response);
    }

    //
    // Helpers
    //

    /**
     * Builds an HTTP client for the LawPay API.
     *
     * @param string|null $ip end user IP address, when available
     */
    private function getClient(PaymentGatewayConfiguration $gatewayConfiguration, ?string $ip = null): Client
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($ip) {
            $headers['X-Relayed-IP-Address'] = $ip;
        }

        return HttpClientFactory::make($this->gatewayLogger, [
            'auth' => [$gatewayConfiguration->credentials->secret_key, ''],
            'base_uri' => self::API_URL,
            'headers' => $headers,
        ]);
    }

    private function performGetRequest(PaymentGatewayConfiguration $gatewayConfiguration, string $endpoint): ResponseInterface
    {
        $client = $this->getClient($gatewayConfiguration);

        return $client->request('GET', $endpoint);
    }

    private function performDeleteRequest(PaymentGatewayConfiguration $gatewayConfiguration, string $endpoint): ResponseInterface
    {
        $client = $this->getClient($gatewayConfiguration);

        return $client->request('DELETE', $endpoint);
    }

    /**
     * Parses a response from the LawPay gateway.
     */
    private function parseResponse(ResponseInterface $response): object
    {
        return json_decode((string) $response->getBody());
    }

    /**
     * Builds an error message.
     */
    private function buildErrorMessage(object $result): string
    {
        $message = [];
        if (isset($result->messages)) {
            foreach ($result->messages as $part) {
                $message[] = $part->message;
            }
        }

        $message = implode(', ', $message);

        if (!$message) {
            return 'An unknown error has occurred.';
        }

        return $message;
    }

    /**
     * Retrieves a charge from LawPay.
     *
     * @throws Exception
     */
    private function retrieveCharge(string $chargeId, PaymentGatewayConfiguration $gatewayConfiguration): ResponseInterface
    {
        try {
            $response = $this->performGetRequest($gatewayConfiguration, 'transactions/'.$chargeId);
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            throw new Exception($this->buildErrorMessage($result));
        }

        return $response;
    }

    private function buildTransactionStatus(object $result): array
    {
        return [
            self::CHARGE_STATUSES[$result->status],
            $this->buildErrorMessage($result),
        ];
    }

    /**
     * Builds a Refund object from an LawPay transaction.
     */
    private function buildRefund(object $result, Money $amount): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result->id,
            status: RefundValueObject::SUCCEEDED,
        );
    }

    /**
     * Refunds a transaction.
     *
     * @throws RefundException when the refund fails
     */
    private function performRefund(MerchantAccount $account, string $chargeId, Money $amount): RefundValueObject
    {
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $params = [
            'amount' => $amount->amount,
        ];

        // perform the call on LawPay
        try {
            $response = $this->performPostRequest($gatewayConfiguration, 'charges/'.$chargeId.'/refund', $params);
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            throw new RefundException($this->buildErrorMessage($result));
        } catch (GuzzleException) {
            throw new RefundException('An unknown error has occurred when communicating with LawPay');
        }

        // parse the response
        $result = $this->parseResponse($response);

        // return a Refund object
        return $this->buildRefund($result, $amount);
    }

    private function performPostRequest(PaymentGatewayConfiguration $gatewayConfiguration, string $endpoint, ?array $params = null, ?string $ip = null): ResponseInterface
    {
        $client = $this->getClient($gatewayConfiguration, $ip);
        if (null === $params) {
            return $client->request('POST', $endpoint);
        }

        $this->gatewayLogger->logJsonRequest($params, self::MASKED_REQUEST_PARAMETERS);

        return $client->request('POST', $endpoint, ['json' => $params]);
    }

    /**
     * Builds an LawPay charge request.
     *
     * @param ReceivableDocument[] $documents
     */
    private function buildLawPayCharge(PaymentGatewayConfiguration $gatewayConfiguration, PaymentSource $source, Money $amount, string $description, array $documents): array
    {
        $accountId = $gatewayConfiguration->credentials->account_id;

        $params = [
            'account_id' => $accountId,
            'currency' => strtoupper($amount->currency),
            'amount' => $amount->amount,
            'data' => [
                'invoiced_customer_id' => (string) $source->customer_id,
            ],
        ];

        // set the transaction metadata
        $params['reference'] = $description;
        if (count($documents) > 0) {
            $params['data']['invoiced_invoice_id'] = (string) $documents[0]->id;
        }

        return $params;
    }

    /**
     * Builds a charge object from a LawPay transaction.
     */
    private function buildCharge(object $result, PaymentSource $source, string $description): ChargeValueObject
    {
        $amount = new Money($result->currency, $result->amount);

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result->id,
            method: '',
            status: self::CHARGE_STATUSES[$result->status],
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $result->status,
        );
    }

    /**
     * Builds a failed charge object from a LawPay transaction.
     */
    private function buildFailedCharge(object $result, object $errorResult, PaymentSource $source, string $description): ChargeValueObject
    {
        $amount = new Money($result->currency, $result->amount);

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result->id,
            method: '',
            status: ChargeValueObject::FAILED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $this->buildErrorMessage($errorResult),
        );
    }

    /**
     * Gets the client.
     */
    private function getFailedCharge(object $result, PaymentSource $source, PaymentGatewayConfiguration $gatewayConfiguration, string $description): ?ChargeValueObject
    {
        $chargeId = false;
        foreach ($result->messages as $part) {
            if (isset($part->entity_id)) {
                $chargeId = $part->entity_id;

                break;
            }
        }

        // retrieve the failed charge
        if (!$chargeId) {
            return null;
        }

        try {
            $response = $this->retrieveCharge($chargeId, $gatewayConfiguration);
            $body = $response->getBody();
            $lawPayCharge = json_decode($body);

            return $this->buildFailedCharge($lawPayCharge, $result, $source, $description);
        } catch (Exception) {
            // do nothing
        }

        return null;
    }

    /**
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function chargeBankAccount(BankAccount $bankAccount, MerchantAccount $account, Money $amount, array $documents, string $description): ChargeValueObject
    {
        // set up the charge
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $chargeParams = $this->buildLawPayCharge($gatewayConfiguration, $bankAccount, $amount, $description, $documents);
        $chargeParams['method'] = $this->buildLawPayBankAccount($bankAccount);

        // perform the charge on LawPay
        try {
            $response = $this->performPostRequest($gatewayConfiguration, 'charges', $chargeParams);
        } catch (ClientException $e) {
            $result = $this->parseResponse($e->getResponse());

            // build a failed charge if possible
            $charge = $this->getFailedCharge($result, $bankAccount, $gatewayConfiguration, $description);

            throw new ChargeException($this->buildErrorMessage($result), $charge);
        } catch (GuzzleException) {
            throw new ChargeException('An unknown error has occurred when communicating with LawPay');
        }

        // parse the response
        $result = $this->parseResponse($response);

        return $this->buildCharge($result, $bankAccount, $description);
    }

    /**
     * Builds an LawPay bank payment method.
     */
    private function buildLawPayBankAccount(BankAccount $bankAccount): array
    {
        $type = self::ACH_TYPE_CHECKING;
        if (BankAccountValueObject::TYPE_SAVINGS == $bankAccount->type) {
            $type = self::ACH_TYPE_SAVINGS;
        }

        return [
            'type' => self::TYPE_BANK_ACCOUNT,
            'account_number' => $bankAccount->account_number,
            'routing_number' => $bankAccount->routing_number,
            'account_type' => $type,
            'name' => $bankAccount->account_holder_name,
        ];
    }
}
