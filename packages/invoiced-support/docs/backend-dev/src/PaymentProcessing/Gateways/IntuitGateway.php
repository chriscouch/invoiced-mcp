<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\OAuthConnectionManager;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksOAuth;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Libs\HttpClientFactory;
use App\PaymentProcessing\Libs\PaymentServerClient;
use App\PaymentProcessing\Libs\RoutingNumberLookup;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class IntuitGateway extends AbstractLegacyGateway implements RefundInterface, TransactionStatusInterface
{
    const ID = 'intuit';

    private const PROD_URL = 'https://api.intuit.com';
    private const SANDBOX_URL = 'https://sandbox.api.intuit.com';

    private const PENDING_CODE = 'PENDING';
    private const DECLINED_CODE = 'DECLINED';
    private const DECLINED_CODES = ['DECLINED', 'CANCELLED'];

    private const CHECKING = 'PERSONAL_CHECKING';
    private const SAVINGS = 'PERSONAL_SAVINGS';

    private const MASKED_REQUEST_PARAMETERS = [
        'number',
        'cvc',
        'accountNumber',
    ];

    private bool $isAchRefund = false;

    public function __construct(
        PaymentServerClient $paymentServerClient,
        GatewayLogger $gatewayLogger,
        RoutingNumberLookup $routingNumberLookup,
        PaymentSourceReconciler $sourceReconciler,
        private OAuthConnectionManager $oauthManager,
        private QuickBooksOAuth $oauth,
    ) {
        parent::__construct($paymentServerClient, $gatewayLogger, $routingNumberLookup, $sourceReconciler);
    }

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->access_token)) {
            throw new InvalidGatewayConfigurationException('Missing Access Token');
        }
    }

    //
    // One-Time Charges
    //

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $this->refreshMerchantAccount($account);

        $paymentMethod = $parameters['payment_method'] ?? '';
        if ('ach' == $paymentMethod) {
            try {
                $bankAccountValueObject = GatewayHelper::makeAchBankAccount($this->routingNumberLookup, $customer, $account, $parameters, false);
                /** @var BankAccount $bankAccountModel */
                $bankAccountModel = $this->sourceReconciler->reconcile($bankAccountValueObject);
            } catch (ReconciliationException|InvalidBankAccountException $e) {
                throw new ChargeException($e->getMessage());
            }

            return $this->chargeBankAccount($account, $bankAccountModel, $amount, $description);
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

        $this->refreshMerchantAccount($account);

        // Other payment types fall back to the payment server
        return parent::vaultSource($customer, $account, $parameters);
    }

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $account = $source->getMerchantAccount();
        $this->refreshMerchantAccount($account);

        if ($source instanceof BankAccount) {
            return $this->chargeBankAccount($account, $source, $amount, $description);
        }

        if ($source instanceof Card) {
            return $this->chargeCard($account, $source, $amount, $description);
        }

        throw new ChargeException('Unknown payment source');
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        [$id, $customerId] = $this->extractIds((string) $source->gateway_id);

        $url = "/quickbooks/v4/customers/$customerId/cards/$id";
        if ($source instanceof BankAccount) {
            $url = "/quickbooks/v4/customers/$customerId/bank-accounts/$id";
        }

        $this->refreshMerchantAccount($account);
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $client = $this->getClient($gatewayConfiguration);

        try {
            $response = $client->request('DELETE', $url, ['headers' => $this->getOAuthHeaders($gatewayConfiguration)]);
        } catch (ClientException $e) {
            throw new PaymentSourceException($this->getResponseError($e->getResponse()));
        } catch (GuzzleException) {
            throw new PaymentSourceException('An unknown error has occurred when communicating with the Intuit payment gateway.');
        }

        $result = $this->parseResponse($response);
        if ($this->isError($result)) {
            throw new PaymentSourceException($this->getFirstErrorMessage($result));
        }
    }

    //
    // Refunds
    //

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        // NOTE: there are different endpoints for credit card and ach refunds
        if ($this->isAchRefund) {
            $url = "/quickbooks/v4/payments/echecks/$chargeId/refunds";
        } else {
            $url = "/quickbooks/v4/payments/charges/$chargeId/refunds";
        }

        $params = [
            'amount' => number_format($amount->toDecimal(), 2, '.', ''),
        ];

        $this->refreshMerchantAccount($merchantAccount);
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();

        try {
            $result = $this->performRequest($gatewayConfiguration, $url, $params);
        } catch (ClientException $e) {
            $response = $e->getResponse();

            // if the response fails with a not found error then try an ach refund
            // this is needed because we do not know whether the charge was a card
            // or bank account charge
            if (404 == $response->getStatusCode()) {
                $this->isAchRefund = true;

                return $this->refund($merchantAccount, $chargeId, $amount);
            }

            throw new RefundException($this->getResponseError($response));
        } catch (GuzzleException) {
            throw new RefundException('An unknown error has occurred when communicating with the Intuit payment gateway.');
        }

        if ($this->isError($result)) {
            throw new RefundException($this->getFirstErrorMessage($result));
        }

        if (in_array($result['status'], self::DECLINED_CODES)) {
            throw new RefundException('The refund was declined by the gateway.');
        }

        return $this->buildRefund($amount, $result);
    }

    //
    // Transaction Status
    //

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        $chargeId = $charge->gateway_id;
        $this->refreshMerchantAccount($merchantAccount);
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $client = $this->getClient($gatewayConfiguration);

        try {
            $url = "/quickbooks/v4/payments/echecks/$chargeId";
            $response = $client->request('GET', $url, ['headers' => $this->getOAuthHeaders($gatewayConfiguration)]);
        } catch (ClientException $e) {
            throw new TransactionStatusException($this->getResponseError($e->getResponse()));
        } catch (GuzzleException) {
            throw new TransactionStatusException('An unknown error has occurred when communicating with the Intuit payment gateway.');
        }

        $result = $this->parseResponse($response);
        if ($this->isError($result)) {
            throw new TransactionStatusException($this->getFirstErrorMessage($result));
        }

        return $this->buildTransactionStatus($result);
    }

    //
    // Helpers
    //

    private function refreshMerchantAccount(MerchantAccount $merchantAccount): void
    {
        // opportunistically check if the access token needs to be refreshed
        $quickbooksAccount = QuickBooksAccount::queryWithTenant($merchantAccount->tenant())->oneOrNull();

        if ($quickbooksAccount instanceof QuickBooksAccount) {
            try {
                $this->oauthManager->refresh($this->oauth, $quickbooksAccount);
                $merchantAccount->refresh();
            } catch (OAuthException) {
                // Do nothing. Allow payments server to handle invalid credentials exception.
            }
        }
    }

    /**
     * Extract id and customer id from payment source id.
     */
    private function extractIds(string $id): array
    {
        return explode(':', $id);
    }

    /**
     * Create Guzzle Client and also check if credentials are set.
     */
    public function getClient(PaymentGatewayConfiguration $gatewayConfiguration): Client
    {
        return HttpClientFactory::make($this->gatewayLogger, [
            'base_uri' => $this->getBaseUrl($gatewayConfiguration),
            'headers' => [
                'Request-ID' => uniqid(),
            ],
        ]);
    }

    /**
     * Return the base url dependent on test_mode.
     */
    private function getBaseUrl(PaymentGatewayConfiguration $gatewayConfiguration): string
    {
        $url = self::PROD_URL;
        if (isset($gatewayConfiguration->credentials->test_mode) && $gatewayConfiguration->credentials->test_mode) {
            $url = self::SANDBOX_URL;
        }

        return $url;
    }

    /**
     * Get OAuth Headers to send with each request.
     */
    private function getOAuthHeaders(PaymentGatewayConfiguration $gatewayConfiguration): array
    {
        return [
            'Authorization' => 'Bearer '.$gatewayConfiguration->credentials->access_token,
        ];
    }

    /**
     * Get the first error from the response.
     */
    private function getResponseError(ResponseInterface $response): string
    {
        if (401 == $response->getStatusCode()) {
            return 'Could not connect to Intuit gateway. The credentials we have were rejected. Please reconnect your account and try again.';
        } elseif (404 == $response->getStatusCode()) {
            return 'Resource not found error returned from the Intuit gateway.';
        }

        $body = (string) $response->getBody();
        if (!$body) {
            return 'Could not connect to Intuit gateway. An unknown error has occurred.';
        }

        $result = $this->parseResponse($response);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return $body;
        }

        return $this->getFirstErrorMessage($result);
    }

    /**
     * Builds an error message from a JSON response.
     */
    private function getFirstErrorMessage(array $result): string
    {
        if (isset($result['errors'][0]['message'])) {
            return $result['errors'][0]['message'];
        }

        return 'An unknown response was returned from the Intuit gateway.';
    }

    /**
     * Parse response from request.
     */
    private function parseResponse(ResponseInterface $response): array
    {
        return (array) json_decode((string) $response->getBody(), true);
    }

    private function isError(array $result): bool
    {
        return array_key_exists('errors', $result) && count($result['errors']) > 0;
    }

    /**
     * Build Transaction status for pending request.
     */
    private function buildTransactionStatus(array $result): array
    {
        $status = ChargeValueObject::SUCCEEDED;
        if (self::PENDING_CODE == $result['status']) {
            $status = ChargeValueObject::PENDING;
        } elseif (self::DECLINED_CODE == $result['status']) {
            $status = ChargeValueObject::FAILED;
        }

        return [$status, $result['status']];
    }

    /**
     * @throws GuzzleException
     */
    private function performRequest(PaymentGatewayConfiguration $gatewayConfiguration, string $url, array $params): array
    {
        $this->gatewayLogger->logJsonRequest($params, self::MASKED_REQUEST_PARAMETERS);

        $client = $this->getClient($gatewayConfiguration);
        $response = $client->request('POST', $url, [
            'json' => $params,
            'headers' => $this->getOAuthHeaders($gatewayConfiguration),
        ]);

        return $this->parseResponse($response);
    }

    public function buildRefund(Money $amount, array $result): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result['id'],
            status: RefundValueObject::SUCCEEDED,
        );
    }

    private function chargeCard(MerchantAccount $account, Card $card, Money $amount, string $description): ChargeValueObject
    {
        $url = '/quickbooks/v4/payments/charges';

        $params = $this->buildCardChargeRequest($card, $amount, $description);

        $gatewayConfiguration = $account->toGatewayConfiguration();

        try {
            $result = $this->performRequest($gatewayConfiguration, $url, $params);
        } catch (ClientException $e) {
            throw new ChargeException($this->getResponseError($e->getResponse()));
        } catch (GuzzleException $e) {
            throw new ChargeException('An unknown error has occurred when communicating with the Intuit payment gateway.');
        }

        if ($this->isError($result)) {
            throw new ChargeException($this->getFirstErrorMessage($result));
        }

        if (in_array($result['status'], self::DECLINED_CODES)) {
            $charge = $this->buildFailedCharge($card, $amount, $result, $description);

            throw new ChargeException('The charge was declined by the gateway.', $charge);
        }

        return $this->buildCharge($card, $amount, $result, $description);
    }

    private function chargeBankAccount(MerchantAccount $account, BankAccount $bankAccount, Money $amount, string $description): ChargeValueObject
    {
        $url = '/quickbooks/v4/payments/echecks';

        $gatewayConfiguration = $account->toGatewayConfiguration();
        $params = $this->buildBankAccountChargeRequest($gatewayConfiguration, $bankAccount, $amount, $description);

        try {
            $result = $this->performRequest($gatewayConfiguration, $url, $params);
        } catch (ClientException $e) {
            throw new ChargeException($this->getResponseError($e->getResponse()));
        } catch (GuzzleException) {
            throw new ChargeException('An unknown error has occurred when communicating with the Intuit payment gateway.');
        }

        if ($this->isError($result)) {
            throw new ChargeException($this->getFirstErrorMessage($result));
        }

        if (in_array($result['status'], self::DECLINED_CODES)) {
            $charge = $this->buildFailedCharge($bankAccount, $amount, $result, $description);

            throw new ChargeException('The charge was declined by the gateway.', $charge);
        }

        return $this->buildCharge($bankAccount, $amount, $result, $description);
    }

    /**
     * Create charge request base parameters.
     */
    private function buildCardChargeRequest(Card $card, Money $amount, string $description): array
    {
        $params = [
            'amount' => number_format($amount->toDecimal(), 2, '.', ''),
            'currency' => strtoupper($amount->currency),
            'description' => $description,
            'context' => [
                'mobile' => false,
                'isEcommerce' => false,
            ],
        ];

        [$id] = $this->extractIds((string) $card->gateway_id);
        $params['cardOnFile'] = $id;

        return $params;
    }

    /**
     * Build charge array for charging a bank account.
     */
    private function buildBankAccountChargeRequest(PaymentGatewayConfiguration $gatewayConfiguration, BankAccount $bankAccount, Money $amount, string $description): array
    {
        $params = [
            'amount' => number_format($amount->toDecimal(), 2, '.', ''),
            'paymentMode' => GatewayHelper::secCodeWeb($gatewayConfiguration),
            'description' => $description,
        ];

        if ($bankAccount->account_number) {
            $params['bankAccount'] = $this->buildBankAccount($bankAccount);
        } else {
            [$id] = $this->extractIds((string) $bankAccount->gateway_id);
            $params['bankAccountOnFile'] = $id;
        }

        return $params;
    }

    /**
     * Build Bank Account array to send to gateway.
     */
    private function buildBankAccount(BankAccount $bankAccount): array
    {
        $type = self::CHECKING;
        if (BankAccountValueObject::TYPE_SAVINGS == $bankAccount->type) {
            $type = self::SAVINGS;
        }

        return [
            'name' => substr((string) $bankAccount->account_holder_name, 0, 64),
            'routingNumber' => $bankAccount->routing_number,
            'accountNumber' => $bankAccount->account_number,
            'accountType' => $type,
            'phone' => '0000000000',
        ];
    }

    public function buildCharge(PaymentSource $source, Money $amount, array $result, string $description): ChargeValueObject
    {
        $total = Money::fromDecimal($amount->currency, $result['amount']);

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $total,
            gateway: $this->getId(),
            gatewayId: $result['id'],
            method: '',
            status: $source instanceof BankAccount ? ChargeValueObject::PENDING : ChargeValueObject::SUCCEEDED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
        );
    }

    private function buildFailedCharge(PaymentSource $source, Money $amount, array $result, string $description): ChargeValueObject
    {
        $total = Money::fromDecimal($amount->currency, $result['amount']);

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $total,
            gateway: self::ID,
            gatewayId: $result['id'],
            method: '',
            status: ChargeValueObject::FAILED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $result['status'],
        );
    }
}
