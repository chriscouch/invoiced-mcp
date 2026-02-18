<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\DebugContext;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * This communicates with the legacy gateway payment server.
 * TODO: this class should be changed to call OPP instead of the Invoiced payment server.
 */
class PaymentServerClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const CONNECT_TIMEOUT = 30; // seconds
    private const READ_TIMEOUT = 80; // seconds

    private const OBJECT_CARD = 'card';
    private const OBJECT_BANK_ACCOUNT = 'bank_account';

    private const METHOD_FOR_SOURCE = [
        self::OBJECT_CARD => PaymentMethod::CREDIT_CARD,
        self::OBJECT_BANK_ACCOUNT => PaymentMethod::ACH,
    ];

    private Client $client;

    public function __construct(
        private PaymentSourceReconciler $paymentSourceReconciler,
        private DebugContext $debugContext,
        private string $paymentsHost,
        private string $paymentsAppId,
        private string $paymentsKey,
    ) {
    }

    //
    // Sources
    //

    /**
     * Vaults a payment source.
     *  TODO: this function should be changed to call OPP instead of the Invoiced payment server.
     *
     * @throws PaymentSourceException
     */
    public function vaultSource(MerchantAccount $merchantAccount, array $parameters, Customer $customer): SourceValueObject
    {
        try {
            $parameters['merchant_account'] = $this->buildMerchantAccount($merchantAccount);
        } catch (InvalidArgumentException $e) {
            throw new PaymentSourceException($e->getMessage());
        }

        try {
            $response = $this->getClient()
                ->request('POST', 'sources', [
                    'json' => $parameters,
                    'headers' => [
                        'X-Tenant-Id' => $merchantAccount->tenant_id,
                        'X-Correlation-Id' => $this->debugContext->getCorrelationId(),
                    ],
                ]);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody());
            if ($body) {
                $message = $body->message;
            } else {
                $message = 'An unknown error occurred when processing your payment information.';
                $this->logger->error($message, ['exception' => $e]);
            }

            throw new PaymentSourceException($message);
        }

        $result = json_decode($response->getBody());
        $receiptEmail = $parameters['email'] ?? null;

        return $this->buildInvoicedSource($customer, $merchantAccount, $result, $receiptEmail);
    }

    //
    // Charges
    //

    /**
     * Initiates a charge.
     * TODO: this function should be changed to call OPP instead of the Invoiced payment server.
     *
     * @throws ChargeException
     */
    public function charge(MerchantAccount $merchantAccount, array $parameters, Customer $customer, Money $amount): ChargeValueObject
    {
        try {
            $parameters['merchant_account'] = $this->buildMerchantAccount($merchantAccount);
        } catch (InvalidArgumentException $e) {
            throw new ChargeException($e->getMessage());
        }
        $parameters['currency'] = $amount->currency;
        $parameters['amount'] = $amount->amount;

        try {
            $response = $this->getClient()
                ->request('POST', 'charges', [
                    'json' => $parameters,
                    'headers' => [
                        'X-Tenant-Id' => $customer->tenant_id,
                        'X-Correlation-Id' => $this->debugContext->getCorrelationId(),
                    ],
                ]);

            $result = json_decode($response->getBody());
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $result = json_decode($response->getBody());
            $result->_error = true;

            // We are intentionally not throwing an exception here
            // to allow a failed charge object to be generated.
        }

        if (isset($result->_error)) {
            //  build a failed charge if it was included in the error response
            $charge = null;
            if (isset($result->charge)) {
                $charge = $this->buildCharge($result->charge, $merchantAccount, $customer, $parameters);
            }

            throw new ChargeException($result->message, $charge);
        }

        return $this->buildCharge($result, $merchantAccount, $customer, $parameters);
    }

    //
    // Helpers
    //

    /**
     * Sets the HTTP client.
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * Gets the HTTP client.
     */
    private function getClient(): Client
    {
        if (!isset($this->client)) {
            $settings = [
                'base_uri' => $this->paymentsHost,
                'auth' => [$this->paymentsAppId, $this->paymentsKey],
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'read_timeout' => self::READ_TIMEOUT,
                'headers' => [
                    'User-Agent' => 'Invoiced/1.0',
                ],
            ];
            $this->client = new Client($settings);
        }

        return $this->client;
    }

    /**
     * Builds the request parameters for a merchant account.
     *
     * @throws InvalidArgumentException
     */
    private function buildMerchantAccount(MerchantAccount $merchantAccount): array
    {
        $credentials = $merchantAccount->credentials;

        // Check if user has setup the gateway yet
        if (!in_array($merchantAccount->gateway, ['test', 'mock']) && '0' == $merchantAccount->gateway_id && 0 == count((array) $credentials)) {
            throw new InvalidArgumentException('The payment gateway has not been configured yet. If you are the seller, then please configure your payment gateway in the Payment Settings in order to process payments.');
        }

        return [
            'gateway' => $merchantAccount->gateway,
            'credentials' => $credentials,
        ];
    }

    /**
     * Builds an Invoiced source from a payment source response.
     *
     * @throws PaymentSourceException
     */
    private function buildInvoicedSource(Customer $customer, MerchantAccount $merchantAccount, object $source, ?string $receiptEmail = null): SourceValueObject
    {
        if (self::OBJECT_CARD == $source->object) {
            return new CardValueObject(
                customer: $customer,
                gateway: $merchantAccount->gateway,
                gatewayId: $source->id,
                gatewayCustomer: $source->customer,
                gatewaySetupIntent: $source->setup_intent ?? null,
                merchantAccount: $merchantAccount,
                chargeable: !empty($source->id),
                receiptEmail: $receiptEmail,
                brand: $source->brand ?? 'Unknown',
                funding: $source->funding ?? 'unknown',
                last4: $source->last4 ?? '0000',
                expMonth: (int) $source->exp_month,
                expYear: (int) $source->exp_year,
                country: $source->issuing_country ?? null,
            );
        }

        if (self::OBJECT_BANK_ACCOUNT == $source->object) {
            return new BankAccountValueObject(
                customer: $customer,
                gateway: $merchantAccount->gateway,
                gatewayId: $source->id,
                gatewayCustomer: $source->customer,
                gatewaySetupIntent: $source->setup_intent ?? null,
                merchantAccount: $merchantAccount,
                chargeable: !empty($source->id),
                receiptEmail: $receiptEmail,
                bankName: $source->bank_name ?? 'Unknown',
                routingNumber: $source->routing_number,
                last4: $source->last4 ?? '0000',
                currency: $source->currency,
                country: $source->country ?? $customer->country,
                accountHolderName: $source->account_holder_name ?? null,
                accountHolderType: $source->account_holder_type ?? null,
                type: $source->type ?? null,
                verified: $source->verified,
            );
        }

        throw new PaymentSourceException('Unsupported source type: '.$source->object);
    }

    /**
     * Builds a charge object from a charge response.
     */
    private function buildCharge(object $result, MerchantAccount $merchantAccount, Customer $customer, array $parameters): ChargeValueObject
    {
        $receiptEmail = $parameters['email'] ?? null;

        $source = null;
        if ($result->source) {
            // build the source value object
            $sourceObj = $this->buildInvoicedSource($customer, $merchantAccount, $result->source, $receiptEmail);

            // then reconcile it
            try {
                $source = $this->paymentSourceReconciler->reconcile($sourceObj);
            } catch (ReconciliationException) {
                // intentionally ignore reconciliation exceptions and
                // leave the payment source null
            }
        }

        $amount = new Money($result->currency, $result->amount);

        $charge = new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: $result->gateway,
            gatewayId: (string) $result->id,
            method: '',
            status: $result->status,
            merchantAccount: $merchantAccount,
            source: $source,
            description: $parameters['description'],
            timestamp: $result->timestamp,
            failureReason: Charge::FAILED == $result->status ? $result->message : null
        );

        if ($method = $source ? $source->getMethod() : $this->paymentMethod($result->source)) {
            $charge = $charge->withMethod($method);
        }

        return $charge;
    }

    /**
     * Gets the Invoiced payment method for a returned payment source.
     */
    protected function paymentMethod(?object $source): ?string
    {
        if (!$source) {
            return null;
        }

        $obj = $source->object;

        return array_value(self::METHOD_FOR_SOURCE, $obj);
    }
}
