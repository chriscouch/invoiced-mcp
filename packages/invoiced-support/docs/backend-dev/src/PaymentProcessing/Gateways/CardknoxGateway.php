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
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TestCredentialsInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\HttpClientFactory;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class CardknoxGateway extends AbstractLegacyGateway implements RefundInterface, TestCredentialsInterface
{
    const ID = 'cardknox';

    private const API_URL = 'https://x1.cardknox.com/gatewayjson';

    private const STATUS_APPROVED = 'A';
    private const STATUS_ERROR = 'E';
    private const STATUS_DECLINED = 'D';

    private const MASKED_REQUEST_PARAMETERS = [
        'xKey',
        'xCardNum',
        'xCVV',
        'xAccount',
    ];

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->key)) {
            throw new InvalidGatewayConfigurationException('Missing Cardknox key!');
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

        // build the request
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $request = $this->buildRequestParameters($gatewayConfiguration);
        if ($source instanceof BankAccount) {
            $request['xCommand'] = 'check:Sale';
        } else {
            $request['xCommand'] = 'cc:Sale';
        }
        $request['xToken'] = $source->gateway_id;
        $request = $this->addSaleTransaction($source->customer, $amount, $documents, $description, $parameters, $request);

        // log the request
        $this->gatewayLogger->logJsonRequest($request, self::MASKED_REQUEST_PARAMETERS);

        // send it to the gateway
        try {
            $response = $this->getClient()->post('', [
                'json' => $request,
            ]);
        } catch (GuzzleException) {
            throw new ChargeException('An unknown error has occurred when communicating with Cardknox');
        }

        // parse the response
        $result = $this->parseResponse($response);

        if (self::STATUS_DECLINED == $result->xResult) {
            throw new ChargeException($result->xError, $this->buildFailedCharge($result, $amount, $source, $description));
        } elseif (self::STATUS_ERROR == $result->xResult) {
            throw new ChargeException($result->xError);
        }

        return $this->buildCharge($result, $amount, $source, $description);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        // Cardknox does not support deleting payment information. Do nothing to let it
        // be deleted from our database.
    }

    //
    // Refunds
    //

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        // build the request
        $gaetwayConfiguration = $merchantAccount->toGatewayConfiguration();
        $request = $this->buildRequestParameters($gaetwayConfiguration);

        // TODO need to know if this was card or ACH and use check:Refund for ACH
        $request['xCommand'] = 'cc:Refund';
        $request['xRefNum'] = $chargeId;
        $request['xAmount'] = $amount->toDecimal();
        $request['xAllowDuplicate'] = true;

        $request = $this->addSaleTransaction(null, $amount, [], 'Refund', [], $request);

        // log the request
        $this->gatewayLogger->logJsonRequest($request, self::MASKED_REQUEST_PARAMETERS);

        // send it to the gateway
        try {
            $response = $this->getClient()->post('', [
                'json' => $request,
            ]);
        } catch (GuzzleException) {
            throw new RefundException('An unknown error has occurred when communicating with Cardknox');
        }

        // parse the response
        $result = $this->parseResponse($response);

        if (self::STATUS_APPROVED != $result->xResult) {
            throw new RefundException($result->xError);
        }

        return $this->buildRefund($result, $amount);
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        // build the request
        $request = $this->buildRequestParameters($gatewayConfiguration);

        $request['xCommand'] = 'cc:Void';
        $request['xRefNum'] = 'not a valid refnum';
        $request['xAllowDuplicate'] = true;

        // log the request
        $this->gatewayLogger->logJsonRequest($request, self::MASKED_REQUEST_PARAMETERS);

        // send it to the gateway
        try {
            $response = $this->getClient()->post('', [
                'json' => $request,
            ]);
        } catch (GuzzleException) {
            throw new TestGatewayCredentialsException('An unknown error has occurred when communicating with Cardknox');
        }

        // parse the response
        $result = $this->parseResponse($response);

        if (in_array($result->xError, ['Specified Key Not Found', 'Required: xKey'])) {
            throw new TestGatewayCredentialsException('The provided key was not valid.');
        }
    }

    //
    // Helpers
    //

    private function getClient(): Client
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        return HttpClientFactory::make($this->gatewayLogger, [
            'base_uri' => self::API_URL,
            'headers' => $headers,
        ]);
    }

    private function buildRequestParameters(PaymentGatewayConfiguration $gatewayConfiguration): array
    {
        return [
            'xKey' => $gatewayConfiguration->credentials->key,
            'xSoftwareName' => 'Invoiced',
            'xSoftwareVersion' => '1.0',
            'xVersion' => '4.5.8',
        ];
    }

    /**
     * Parses a response from the Cardknox gateway.
     */
    private function parseResponse(ResponseInterface $response): object
    {
        $body = (string) $response->getBody();

        // Sometimes we get a form-encoded value instead of JSON
        if ('{' !== substr($body, 0, 1)) {
            parse_str($body, $result);
            $result = (object) $result;
        } else {
            $result = json_decode($body);
        }

        return $result;
    }

    /**
     * @param ReceivableDocument[] $documents
     */
    private function addSaleTransaction(?Customer $customer, Money $amount, array $documents, string $description, array $parameters, array $request): array
    {
        $request['xCurrency'] = strtoupper($amount->currency);
        $request['xAmount'] = $amount->toDecimal();
        $request['xAllowDuplicate'] = true;

        // set transaction metadata
        $request['xDescription'] = $description;
        if ($customer) {
            if ($email = GatewayHelper::getEmail($customer, $parameters)) {
                $request['xEmail'] = $email;
            }
            $request['xCustom01'] = (string) $customer->id;
        }
        if (count($documents) > 0) {
            $request['xInvoice'] = (string) $documents[0]->id;
        }

        return $request;
    }

    /**
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function chargeBankAccount(BankAccount $bankAccount, MerchantAccount $account, Money $amount, array $parameters, array $documents, string $description): ChargeValueObject
    {
        // build the request
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $request = $this->buildRequestParameters($gatewayConfiguration);
        $request = $this->addBankAccount($bankAccount, $request);
        $request['xCommand'] = 'check:Sale';
        $request = $this->addSaleTransaction($bankAccount->customer, $amount, $documents, $description, $parameters, $request);

        // log the request
        $this->gatewayLogger->logJsonRequest($request, self::MASKED_REQUEST_PARAMETERS);

        // send it to the gateway
        try {
            $response = $this->getClient()->post('', [
                'json' => $request,
            ]);
        } catch (GuzzleException $e) {
            throw new ChargeException('An unknown error has occurred when communicating with Cardknox');
        }

        // parse the response
        $result = $this->parseResponse($response);

        if (self::STATUS_DECLINED == $result->xResult) {
            throw new ChargeException($result->xError, $this->buildFailedCharge($result, $amount, $bankAccount, $description));
        } elseif (self::STATUS_ERROR == $result->xResult) {
            throw new ChargeException($result->xError);
        }

        return $this->buildCharge($result, $amount, $bankAccount, $description);
    }

    private function addBankAccount(BankAccount $bankAccount, array $parameters): array
    {
        $parameters['xAccount'] = $bankAccount->account_number;
        $parameters['xRouting'] = $bankAccount->routing_number;
        $parameters['xName'] = $bankAccount->account_holder_name;

        return $parameters;
    }

    /**
     * Builds a Refund object from a Cardknox transaction.
     */
    private function buildRefund(object $result, Money $amount): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result->xRefNum,
            status: RefundValueObject::SUCCEEDED,
        );
    }

    /**
     * Builds a charge object from a Cardknox transaction.
     *
     * @param object $result
     */
    private function buildCharge($result, Money $amount, PaymentSource $source, string $description): ChargeValueObject
    {
        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result->xRefNum,
            method: '',
            status: ChargeValueObject::SUCCEEDED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $result->xStatus,
        );
    }

    /**
     * Builds a failed charge object from a Cardknox transaction.
     *
     * @param object $result
     */
    private function buildFailedCharge($result, Money $amount, PaymentSource $source, string $description): ChargeValueObject
    {
        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result->xRefNum,
            method: '',
            status: ChargeValueObject::FAILED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $result->xError,
        );
    }
}
