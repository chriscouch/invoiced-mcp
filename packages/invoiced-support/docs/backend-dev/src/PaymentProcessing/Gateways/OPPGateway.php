<?php

namespace App\PaymentProcessing\Gateways;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\RandomString;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\VoidException;
use App\PaymentProcessing\Interfaces\VoidInterface;
use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Opp\OPPClient;
use App\Integrations\Opp\OPPClientFactory;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TestCredentialsInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Interfaces\VerifyBankAccountInterface;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Libs\PaymentServerClient;
use App\PaymentProcessing\Libs\RoutingNumberLookup;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class OPPGateway extends AbstractGateway implements LoggerAwareInterface, TestCredentialsInterface, RefundInterface, TransactionStatusInterface, VerifyBankAccountInterface, VoidInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    const string ID = 'OPP';

    private OPPClient $oppClient;

    public function __construct(
        private readonly OPPClientFactory        $OPPClientFactory,
        private readonly PaymentSourceReconciler $paymentSourceReconciler,
        PaymentServerClient                      $paymentServerClient,
        GatewayLogger                            $gatewayLogger,
        RoutingNumberLookup                      $routingNumberLookup,
        PaymentSourceReconciler                  $sourceReconciler,
    ) {
        parent::__construct($paymentServerClient, $gatewayLogger, $routingNumberLookup, $sourceReconciler);
    }


    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->key) || !isset($gatewayConfiguration->credentials->accessToken)) {
            throw new InvalidGatewayConfigurationException('Missing Opp key or token!');
        }
    }

    //
    // One-Time Charges
    //
    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $oppClient = $this->getOPP($gatewayConfiguration);

        $paymentMethod = $parameters['method'] ?? '';
        if (!in_array($paymentMethod, [PaymentMethod::ACH, PaymentMethod::CREDIT_CARD])) {
            throw new ChargeException('Unsupported payment method');
        }

        try {
            $confirmPaymentMethodPayload = $this->buildConfirmPaymentMethodPayload($parameters);
            $confirmResponse = $oppClient->confirmPaymentMethod($parameters['customer_token'], $confirmPaymentMethodPayload);

            if (!isset($confirmResponse['operationResult']) || !$confirmResponse['operationResult'] || !isset($confirmResponse['operationResultObject'])) {
                throw new ChargeException('Failed to confirm payment method with OPP');
            }
        } catch (IntegrationApiException $e) {
            throw new ChargeException($e->getMessage());
        }

        $responsePayload = $confirmResponse['operationResultObject'];
        $paymentMethodToken = $responsePayload['token']['value'];

        $transactionPayload = $this->buildTransactionPayload($parameters['identifier'], $description, $amount);

        try {
            $paymentResponse = $oppClient->makePayment($parameters['customer_token'], $paymentMethodToken, $transactionPayload);

            if (!isset($paymentResponse['operationResult']) || !$paymentResponse['operationResult'] || !isset($paymentResponse['operationResultObject'])) {
                throw new ChargeException('Failed to create payment with OPP');
            }
        } catch (IntegrationApiException $e) {
            throw new ChargeException($e->getMessage());
        }


        $source = null;
        $chargeStatus = $paymentResponse['operationResultObject']['status'] ?? null;
        if ("SUCCESS" === $chargeStatus) {
            $sourceObj = $this->buildSource($customer, $account, $confirmResponse, $parameters, $paymentMethod, false);
            try {
                $source = $this->paymentSourceReconciler->reconcile($sourceObj);
            } catch (ReconciliationException) {
                // intentionally ignore reconciliation exceptions and
                // leave the payment source null
            }
        }

        return $this->buildChargeResponse($customer, $source, $amount, $paymentResponse, $account, $description, $paymentMethod);
    }

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $merchantAccount = $source->getMerchantAccount();
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $oppClient = $this->getOPP($gatewayConfiguration);

        if (!($source instanceof BankAccount) && !($source instanceof Card)) {
            throw new ChargeException('Unsupported payment method');
        }

        $paymentMethodToken = $source->gateway_id;
        $customerToken = $source->gateway_customer;
        $reference = $parameters['payment_flow'] ?? $source->customer->client_id . '-' . RandomString::generate(24);

        $transactionPayload = $this->buildTransactionPayload($reference, $description, $amount);
        $transactionPayload = $this->appendRecurringParametersToPayload($transactionPayload);

        try {
            $paymentResponse = $oppClient->makePayment($customerToken, $paymentMethodToken, $transactionPayload);

            if (!isset($paymentResponse['operationResult']) || !$paymentResponse['operationResult'] || !isset($paymentResponse['operationResultObject'])) {
                throw new ChargeException('Failed to create payment with OPP');
            }
        } catch (IntegrationApiException $e) {
            throw new ChargeException($e->getMessage());
        }

        return $this->buildChargeResponse($source->customer, $source, $amount, $paymentResponse, $merchantAccount, $description, $source instanceof BankAccount ? PaymentMethod::ACH : PaymentMethod::CREDIT_CARD);
    }

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $oppClient = $this->getOPP($gatewayConfiguration);

        $paymentMethod = $parameters['method'] ?? '';
        if (!in_array($paymentMethod, [PaymentMethod::ACH, PaymentMethod::CREDIT_CARD])) {
            throw new ChargeException('Unsupported payment method');
        }

        try {
            $confirmPaymentMethodPayload = $this->buildConfirmPaymentMethodPayload($parameters);
            $confirmResponse = $oppClient->confirmPaymentMethod($parameters['customer_token'], $confirmPaymentMethodPayload);

            if (!isset($confirmResponse['operationResult']) || !$confirmResponse['operationResult'] || !isset($confirmResponse['operationResultObject'])) {
                throw new PaymentSourceException('Failed to confirm payment method with OPP');
            }
        } catch (IntegrationApiException $e) {
            throw new PaymentSourceException($e->getMessage(), $e->getCode(), $e);
        }


        return $this->buildSource($customer, $account, $confirmResponse, $parameters, $paymentMethod, true);
    }

    private function buildSource(Customer $customer, MerchantAccount $account, array $confirmResponse, array $parameters, string $paymentMethod, bool $chargeable): SourceValueObject
    {
        $responsePayload = $confirmResponse['operationResultObject'];
        $paymentMethodToken = $responsePayload['token']['value'];

        if (PaymentMethod::CREDIT_CARD == $paymentMethod) {
            return new CardValueObject(
                customer: $customer,
                gateway: self::ID,
                gatewayId: $paymentMethodToken,
                gatewayCustomer: $parameters['customer_token'],
                gatewaySetupIntent: null,
                merchantAccount: $account,
                chargeable: $chargeable,
                receiptEmail: $parameters['receipt_email'] ?? null,
                brand: $responsePayload['type'] ?? 'Unknown',
                funding: 'unknown',
                last4: $responsePayload['lastFour'] ?? '0000',
                expMonth: $responsePayload['expireMonth'],
                expYear: $responsePayload['expireYear'],
                country: $responsePayload['billingAddress']['country'],
            );
        }
        return new BankAccountValueObject(
            customer: $customer,
            gateway: self::ID,
            gatewayId: $paymentMethodToken,
            gatewayCustomer: $parameters['customer_token'],
            gatewaySetupIntent: null,
            merchantAccount: $account,
            chargeable: $chargeable,
            receiptEmail: $parameters['receipt_email'] ?? null,
            bankName: 'Unknown',
            routingNumber: $responsePayload['routingNumber'],
            last4: $responsePayload['lastFour'] ?: '0000',
            currency: 'usd',
            country: 'US',
            accountHolderName: $responsePayload['accountFirstName'].' '.$responsePayload['accountLastName'],
            accountHolderType: $parameters['account_holder_type'],
            type: strtolower($responsePayload['type']),
            verified: true,
        );
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $oppClient = $this->getOPP($gatewayConfiguration);

        if (!($source instanceof BankAccount) && !($source instanceof Card)) {
            throw new PaymentSourceException('Unsupported payment method');
        }

        $customerToken = $source->gateway_customer;

        try {
            $deletePaymentMethodPayload = $this->buildDeletePaymentMethodPayload($source);
            $confirmResponse = $oppClient->deletePaymentMethod($customerToken, $deletePaymentMethodPayload);

            if (!isset($confirmResponse['operationResult']) || !$confirmResponse['operationResult']) {
                throw new PaymentSourceException('Failed to delete payment method with OPP');
            }
        } catch (IntegrationApiException $e) {
            throw new PaymentSourceException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function verifyBankAccount(MerchantAccount $merchantAccount, BankAccount $bankAccount, int $amount1, int $amount2): void
    {
        //DO NOTHING
    }

    //
    // Refunds
    //
    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        /** @var Charge $charge */
        $charge = Charge::where('gateway_id', $chargeId)
            ->where('gateway', OPPGateway::ID)
            ->one();
        $paymentSource = $charge->payment_source;

        if ((!$paymentSource instanceof BankAccount) && (!$paymentSource instanceof Card)) {
            throw new RefundException('Unsupported payment method');
        }

        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $oppClient = $this->getOPP($gatewayConfiguration);

        try {
            $transactionResponse = $oppClient->getTransactionStatus($chargeId);
        } catch (IntegrationApiException $e) {
            throw new RefundException($e->getMessage(), $e->getCode(), $e);
        }

        $paymentMethodToken = $paymentSource->gateway_id;
        $customerToken = $paymentSource->gateway_customer;
        $invoiceId = $transactionResponse['invoiceNumber'];

        try {
            $this->void($merchantAccount, $chargeId, $paymentSource);

            return $this->buildSuccessfulRefundResponseForVoid($chargeId, $amount);
        } catch (VoidException) {
            //DO NOTHING
        }

        $refundPayload = $this->buildRefundPayload($chargeId, $invoiceId, $amount);

        try {
            $refundResponse = $oppClient->makeRefund($customerToken, $paymentMethodToken, $refundPayload);

            if (!isset($refundResponse['operationResult']) || !$refundResponse['operationResult'] || !isset($refundResponse['operationResultObject'])) {
                throw new RefundException('Failed to create refund with OPP');
            }
        } catch (IntegrationApiException $e) {
            throw new RefundException($e->getMessage(), $e->getCode(), $e);
        }
        return $this->buildRefundResponse($refundResponse, $amount);
    }

    //
    // Transaction Status
    //
    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $oppClient = $this->getOPP($gatewayConfiguration);

        try {
            $transactionResponse = $oppClient->getTransactionStatus($charge->gateway_id);
        } catch (IntegrationApiException $e) {
            throw new TransactionStatusException($e->getMessage(), $e->getCode(), $e);
        }
        return $this->buildTransactionStatusResponse($transactionResponse);
    }

    public function void(MerchantAccount $merchantAccount, string $chargeId, ?PaymentSource $paymentSource = null): void
    {
        if ((!$paymentSource instanceof BankAccount) && (!$paymentSource instanceof Card)) {
            throw new VoidException('Unsupported payment method');
        }

        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $oppClient = $this->getOPP($gatewayConfiguration);

        $paymentMethodToken = $paymentSource->gateway_id;
        $customerToken = $paymentSource->gateway_customer;

        $voidPayload = $this->buildVoidPayload($chargeId);

        try {
            $voidResponse = $oppClient->voidPayment($customerToken, $paymentMethodToken, $voidPayload);

            if (!isset($voidResponse['operationResult']) || !$voidResponse['operationResult'] || !isset($voidResponse['operationResultObject']) || 'VOID' !== $voidResponse['operationResultObject']['status']) {
                throw new VoidException('Failed to void payment with OPP');
            }
        } catch (IntegrationApiException $e) {
            throw new VoidException($e->getMessage(), $e->getCode(), $e);
        }
    }

    //
    // Test Credentials
    //
    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        //DO NOTHING
    }

    /**
     * Sets the OPP API key to the merchant's key.
     *
     * @throws InvalidArgumentException when the Opp account does not exist
     */
    public function getOPP(PaymentGatewayConfiguration $gatewayConfiguration): OPPClient
    {
        $accessToken = $gatewayConfiguration->credentials->accessToken ?? '';
        $key = $gatewayConfiguration->credentials->key ?? '';
        if (!$accessToken || !$key) {
            throw new InvalidArgumentException('The payment gateway has not been configured yet. If you are the seller, then please configure your payment gateway in the Payment Settings in order to process payments.');
        }

        if (isset($this->oppClient)) { //only exists when in test scenario
            return $this->oppClient;
        }

        return $this->OPPClientFactory->createOPPClient($accessToken, $key);
    }

    /**
     * Used for testing. DO NOT REMOVE!!!
     */
    public function setOppClient(OPPClient $oppClient): void
    {
        $this->oppClient = $oppClient;
    }

    /**
     * @param array $parameters
     * @return array[]
     */
    private function buildConfirmPaymentMethodPayload(array $parameters): array
    {
        return [
            'paymentMethod' => [
                'token' => [
                    'value' => $parameters['short_payment_method_token']
                ],
                'lastFour' => $parameters['last_four']
            ]
        ];
    }

    /**
     * @param Card|BankAccount $paymentSource
     * @return array[]
     */
    private function buildDeletePaymentMethodPayload(Card|BankAccount $paymentSource): array
    {
        return [
            'paymentMethod' => [
                'token' => [
                    'value' => $paymentSource->gateway_id
                ],
                'lastFour' => $paymentSource->last4
            ]
        ];
    }

    private function buildTransactionPayload(string $reference, string $invoiceNumber, Money $amount): array
    {
        return [
            'type' => 'PAYMENT',
            'category' => 'PAYMENT',
            'invoiceNumber' => substr($invoiceNumber, 0, 20), //OPP supports max 20 chars for this field
            'descriptor' => $reference,
            'amount' => $amount->toDecimal(),
        ];
    }

    /**
     * @param array $transactionPayload
     * @return array|string[]
     */
    private function appendRecurringParametersToPayload(array $transactionPayload): array
    {
        $transactionPayload += ['initiator' => 'MERCHANT'];
        $transactionPayload += ['recurringModel' => 'UNSCHEDULED'];
        $transactionPayload += ['mandateId' => '']; //TODO maybe we need to store and retrieve this?
        return $transactionPayload;
    }

    /**
     * @param string $transactionId
     * @param string $reference
     * @param Money $amount
     * @return array
     */
    private function buildRefundPayload(string $transactionId, string $reference, Money $amount): array
    {
        return [
            'masterTransaction' => [
                'id' => $transactionId
            ],
            'type' => 'REFUND',
            'category' => 'REFUND',
            'invoiceNumber' => $reference,
            'amount' => (string) $amount->toDecimal(),
        ];
    }

    /**
     * Builds a transaction status object from a OPP API response.
     */
    private function buildTransactionStatusResponse(array $transactionStatusResponse): array
    {
        switch ($transactionStatusResponse['status']) {
            case 'SUCCESS':
                $status = ChargeValueObject::SUCCEEDED;
                break;
            case 'UNKNOWN':
                $status = ChargeValueObject::PENDING;
                break;
            case 'ERROR':
                $status = ChargeValueObject::FAILED;
                break;
            default:
                $status = ChargeValueObject::PENDING;
        }

        return [$status, $transactionStatusResponse['responseReasonText']];
    }

    /**
     * Builds a refund transaction status object from a OPP API response.
     */
    private function buildRefundResponse(array $refundResponse, Money $amount): RefundValueObject
    {
        $status = match ($refundResponse['operationResultObject']['status']) {
            'SUCCESS' => RefundValueObject::SUCCEEDED,
            'ERROR', 'FAILURE' => RefundValueObject::FAILED,
            default => RefundValueObject::PENDING,
        };

        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $refundResponse['operationResultObject']['id'],
            status: $status
        );
    }

    /**
     * Builds a refund transaction status object for a successful Void OPP API response.
     */
    private function buildSuccessfulRefundResponseForVoid(string $chargeId, Money $amount): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $chargeId,
            status: RefundValueObject::SUCCEEDED
        );
    }

    /**
     * @param Customer $customer
     * @param PaymentSource|null $source
     * @param Money $amount
     * @param array $paymentResponse
     * @param MerchantAccount $merchantAccount
     * @param string $description
     * @param string $paymentMethod
     * @return ChargeValueObject
     */
    public function buildChargeResponse(Customer $customer, ?PaymentSource $source, Money $amount, array $paymentResponse, MerchantAccount $merchantAccount, string $description, string $paymentMethod): ChargeValueObject
    {
        $status = match ($paymentResponse['operationResultObject']['status']) {
            'SUCCESS' => ChargeValueObject::SUCCEEDED,
            'ERROR', 'FAILURE' => ChargeValueObject::FAILED,
            default => ChargeValueObject::PENDING,
        };

        $charge = new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $paymentResponse['operationResultObject']['id'],
            method: $paymentMethod,
            status: $status,
            merchantAccount: $merchantAccount,
            source: $source,
            description: $description,
            failureReason: $paymentResponse['operationResultObject']['response']['reason'] ?? null,
        );

        if ($source) {
            $charge = $charge->withMethod($source->getMethod());
        }

        if (ChargeValueObject::FAILED === $status) {
            throw new ChargeException('We were unable to process your payment.', $charge);
        }

        return $charge;
    }

    /**
     * @param string $chargeId
     * @return string[]
     */
    public function buildVoidPayload(string $chargeId): array
    {
        return ['paymentGatewayTransactionId' => $chargeId];
    }
}
