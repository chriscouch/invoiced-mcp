<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Exceptions\TestGatewayCredentialsException;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Exceptions\VerifyBankAccountException;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TestCredentialsInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Interfaces\VerifyBankAccountInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Models\TokenizationFlow;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\Level3Data;
use App\PaymentProcessing\ValueObjects\PaymentFlowReconcileData;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Stripe\ApiResponse as StripeApiResponse;
use Stripe\Charge as StripeCharge;
use Stripe\Customer as StripeCustomer;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\ExceptionInterface as StripeError;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequest;
use Stripe\PaymentMethod;
use Stripe\Refund as StripeRefund;
use Stripe\Source;
use Stripe\StripeClient;

class StripeGateway extends AbstractGateway implements StatsdAwareInterface, LoggerAwareInterface, TestCredentialsInterface, RefundInterface, TransactionStatusInterface, VerifyBankAccountInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    const ID = 'stripe';

    public const METADATA_STRIPE_CUSTOMER = 'stripe_customer_id';
    private const STATUS_VERIFIED = 'verified';

    private const MASKED_REQUEST_PARAMETERS = [
        'number',
        'cvc',
        'account_number',
    ];

    private StripeClient $stripe;

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->key)) {
            throw new InvalidGatewayConfigurationException('Missing Stripe key!');
        }
    }

    //
    // One-Time Charges
    //

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $stripe = $this->getStripe($gatewayConfiguration);

        if (isset($parameters['gateway_token'])) {
            return $this->chargeGatewayToken($stripe, $customer, $account, $amount, $parameters, $description, $documents);
        }

        if (isset($parameters['payment_intent'])) {
            return $this->chargePaymentIntent($stripe, $customer, $account, $amount, $parameters, $description, $documents);
        }

        // Other payment types fall back to the payment server
        $this->statsd->increment('payments.stripe.legacy_charge', 1);
        return parent::charge($customer, $account, $amount, $parameters, $description, $documents);
    }

    //
    // Payment Sources
    //

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $account = $source->getMerchantAccount();
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $stripe = $this->getStripe($gatewayConfiguration);

        // Card payment sources and Stripe Payment Methods should be charged through a payment intent
        if ($source instanceof Card || $this->isPaymentMethod((string) $source->gateway_id)) {
            return $this->performPaymentIntent($stripe, $source->customer, $account, $source, $amount, $documents, $description, $parameters);
        }

        return $this->performStripeCharge($stripe, $source->customer, $account, $source, $amount, $documents, $parameters, $description);
    }

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        try {
            $stripe = $this->getStripe($account->toGatewayConfiguration());
        } catch (InvalidArgumentException $e) {
            throw new PaymentSourceException($e->getMessage(), $e->getCode(), $e);
        }

        // Create or retrieve the Stripe customer
        $stripeCustomer = $this->findOrCreateStripeCustomer($customer, $stripe);
        if (!$stripeCustomer) {
            throw new PaymentSourceException('Failed to create the customer on Stripe');
        }

        // Vault it on the Invoiced payment system
        $parameters['stripe_customer_id'] = $stripeCustomer->id;

        //unset the amount to avoid duplicate charge
        unset($parameters['amount'], $parameters['Invoice'], $parameters['amount_type']);

        if (isset($parameters['gateway_token'])) {
            return $this->vaultGatewayToken($stripe, $customer, $account, $parameters);
        }

        // Handle ACH payment information
        $paymentMethod = $parameters['payment_method'] ?? '';
        if ('ach' == $paymentMethod) {
            return $this->vaultBankAccount($stripe, $customer, $account, $parameters);
        }

        $this->statsd->increment('payments.stripe.legacy_vault', 1);
        return parent::vaultSource($customer, $account, $parameters);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        $stripe = $this->getStripe($account->toGatewayConfiguration());

        try {
            $stripeSource = $stripe->customers->deleteSource((string) $source->gateway_customer, (string) $source->gateway_id);
        } catch (StripeError $e) {
            $msg = $this->buildErrorMessage($e);

            // When the payment source cannot be found
            // then we can say it was deleted.
            if ($e instanceof ApiErrorException && 404 == $e->getHttpStatus()) {
                return;
            }

            throw new PaymentSourceException($msg);
        }

        // this can be used to log the gateway response
        $this->logGatewayResponse($stripeSource->getLastResponse());
    }

    public function verifyBankAccount(MerchantAccount $merchantAccount, BankAccount $bankAccount, int $amount1, int $amount2): void
    {
        $stripe = $this->getStripe($merchantAccount->toGatewayConfiguration());

        $params = ['amounts' => [$amount1, $amount2]];

        try {
            $this->gatewayLogger->logJsonRequest($params, self::MASKED_REQUEST_PARAMETERS);

            /** @var Source $source */
            $source = $stripe->customers->retrieveSource((string) $bankAccount->gateway_customer, (string) $bankAccount->gateway_id);
            $source->verify($params);
        } catch (StripeError $e) {
            if ($e instanceof ApiErrorException) {
                $this->gatewayLogger->logStringResponse((string) $e->getHttpBody());
            }

            $message = $e->getMessage();

            if (str_contains($message, 'has already been verified')) {
                return;
            }

            if (str_contains($message, 'Invalid integer') || str_contains($message, 'Array must contain only valid integer_strings') || str_contains($message, 'You must provide the \'amounts\' parameter as an array of two integers.')) {
                throw new VerifyBankAccountException('We were unable to verify the amounts you supplied against the verification amounts sent to your bank account. Please check your bank statement to confirm that you have entered in the correct amounts.');
            }

            throw new VerifyBankAccountException($e->getMessage());
        }

        // this can be used to log the gateway response
        $this->logGatewayResponse($source->getLastResponse());

        if (self::STATUS_VERIFIED == $source->status) {
            return;
        }

        throw new VerifyBankAccountException('Your bank account could not be successfully verified.');
    }

    //
    // Refunds
    //

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        $stripe = $this->getStripe($merchantAccount->toGatewayConfiguration());

        // perform refund on stripe merchant account
        try {
            $params = [
                'amount' => $amount->amount,
                'charge' => $chargeId,
                'metadata' => [
                    'from_invoiced' => true,
                ],
            ];

            $this->gatewayLogger->logJsonRequest($params, self::MASKED_REQUEST_PARAMETERS);

            $stripeRefund = $stripe->refunds->create($params);
        } catch (StripeError $e) {
            throw new RefundException($this->buildErrorMessage($e));
        }

        // this can be used to log the gateway response
        $this->logGatewayResponse($stripeRefund->getLastResponse());

        // parse the result
        return $this->buildRefund($stripeRefund, $amount);
    }

    //
    // Transaction Status
    //

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        $chargeId = $charge->gateway_id;
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $stripe = $this->getStripe($gatewayConfiguration);

        try {
            $charge = $stripe->charges->retrieve($chargeId);
        } catch (StripeError $e) {
            throw new TransactionStatusException($this->buildErrorMessage($e));
        }

        $this->logGatewayResponse($charge->getLastResponse());

        return $this->buildTransactionStatus($charge);
    }

    public function searchTransaction(MerchantAccount $merchantAccount, string $identifier): array
    {
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $stripe = $this->getStripe($gatewayConfiguration);

        try {
            $charge = $stripe->charges->search([
                'query' => "metadata['invoiced.payment_flow']:'$identifier'",
            ]);
        } catch (StripeError $e) {
            throw new TransactionStatusException($this->buildErrorMessage($e));
        }

        $this->logGatewayResponse($charge->getLastResponse());

        return array_map(function ($data) {
            $result = $data->toArray();
            $status = $this->buildTransactionStatus($data);
            $result['invoicedStatus'] = [
                'status' => $status[0],
                'failureMessage' => $status[1],
            ];

            return $result;
        }, $charge->data ?? []);
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        $stripe = $this->getStripe($gatewayConfiguration);

        try {
            $stripe = $stripe->countrySpecs->retrieve('US');
        } catch (AuthenticationException $e) {
            $this->gatewayLogger->logStringResponse((string) $e->getHttpBody());

            throw new TestGatewayCredentialsException($e->getMessage());
        }

        $this->logGatewayResponse($stripe->getLastResponse());
    }

    //
    // Helpers
    //

    /**
     * Sets the Stripe API key to the merchant's key. Since
     * the Stripe client library uses the singleton pattern
     * it's super important to call this method once before
     * performing any API calls on behalf of a company.
     *
     * @throws InvalidArgumentException when the Stripe account does not exist
     */
    public function getStripe(PaymentGatewayConfiguration $gatewayConfiguration): StripeClient
    {
        $key = $gatewayConfiguration->credentials->key ?? '';
        if (!$key) {
            throw new InvalidArgumentException('The payment gateway has not been configured yet. If you are the seller, then please configure your payment gateway in the Payment Settings in order to process payments.');
        }

        if (isset($this->stripe)) {
            return $this->stripe;
        }

        return new StripeClient([
            'api_key' => $key,
            'stripe_version' => '2020-08-27',
        ]);
    }

    /**
     * Only used for testing.
     */
    public function setStripe(StripeClient $stripe): void
    {
        $this->stripe = $stripe;
    }

    /**
     * Retrieves or creates a Stripe Customer for an Invoiced customer.
     *
     * @return StripeCustomer|null
     */
    public function findOrCreateStripeCustomer(Customer $customer, StripeClient $stripe)
    {
        $metadata = $customer->metadata;
        $stripeCustomerId = $metadata->{self::METADATA_STRIPE_CUSTOMER} ?? null;

        try {
            // create the customer on stripe
            if (!$stripeCustomerId) {
                $stripeCustomer = $stripe->customers->create($this->buildStripeCustomerParams($customer));
                $this->createStripeCustomerLink($customer, $stripeCustomer->id);

                return $stripeCustomer;
            }

            // retrieve the customer from stripe
            $stripeCustomer = $stripe->customers->retrieve($stripeCustomerId);

            // if the stripe customer does not exist, try creating one
            if (isset($stripeCustomer->deleted) && $stripeCustomer->deleted) {
                $this->deleteStripeCustomerLink($customer);

                return $this->findOrCreateStripeCustomer($customer, $stripe);
            }

            return $stripeCustomer;
        } catch (StripeError $e) {
            // if the stripe customer does not exist, try creating one
            if ($e instanceof StripeInvalidRequest && 404 == $e->getHttpStatus()) {
                $this->deleteStripeCustomerLink($customer);

                return $this->findOrCreateStripeCustomer($customer, $stripe);
            }

            $this->logger->error('Unable to fetch customer from Stripe', ['exception' => $e]);
        }

        return null;
    }

    /**
     * Builds the parameters for saving an Invoiced customer to Stripe.
     */
    public function buildStripeCustomerParams(Customer $customer): array
    {
        $params = [
            'name' => $customer->name,
            'description' => $customer->number,
            'metadata' => [
                'invoiced.customer' => (string) $customer->id,
            ],
        ];

        if ($email = $customer->emailAddress()) {
            $params['email'] = $email;
        }

        if ($phone = $customer->phone) {
            $params['phone'] = substr($phone, 0, 20);
        }

        if ($customer->address1) {
            $params['address'] = [
                'line1' => $customer->address1,
                'line2' => $customer->address2,
                'city' => $customer->city,
                'state' => $customer->state,
                'postal_code' => $customer->postal_code,
                'country' => $customer->country,
            ];
        }

        return $params;
    }

    /**
     * Creates a Stripe customer link to the customer.
     */
    private function createStripeCustomerLink(Customer $customer, string $stripeId): void
    {
        $metadata = $customer->metadata;
        $k = self::METADATA_STRIPE_CUSTOMER;
        $metadata->$k = $stripeId;
        $customer->metadata = $metadata;
        $customer->saveOrFail();
    }

    /**
     * Removes the Stripe customer link to the customer.
     */
    private function deleteStripeCustomerLink(Customer $customer): void
    {
        $metadata = $customer->metadata;
        if (isset($metadata->{self::METADATA_STRIPE_CUSTOMER})) {
            unset($metadata->{self::METADATA_STRIPE_CUSTOMER});
            $customer->metadata = $metadata;
            $customer->saveOrFail();
        }
    }

    /**
     * Creates a setup intent for adding a payment method on file.
     *
     * @throws PaymentSourceException
     */
    public function createSetupIntent(MerchantAccount $merchantAccount, ?Customer $customer, TokenizationFlow $flow, array $paymentMethodTypes): string
    {
        $stripe = $this->getStripe($merchantAccount->toGatewayConfiguration());

        $params = [
            'usage' => 'off_session',
            'payment_method_types' => $paymentMethodTypes,
            'metadata' => [
                'invoiced.tokenization_flow' => $flow->identifier,
            ],
        ];

        if ($customer?->id) {
            $stripeCustomer = $this->findOrCreateStripeCustomer($customer, $stripe);
            if ($stripeCustomer) {
                $params['customer'] = $stripeCustomer->id;
            }
        }

        try {
            $setupIntent = $stripe->setupIntents->create($params);
        } catch (StripeError $e) {
            $this->logger->error('Unable to create setup intent on Stripe', ['exception' => $e]);

            throw new PaymentSourceException($e->getMessage());
        }

        return (string) $setupIntent->client_secret;
    }

    /**
     * Gets the next action URL for a setup intent, if any.
     */
    public function getSetupIntentNextActionUrl(PaymentSource $paymentSource): ?string
    {
        $setupIntentId = $paymentSource->gateway_setup_intent;
        if (!$setupIntentId) {
            return null;
        }

        $stripe = $this->getStripe($paymentSource->getMerchantAccount()->toGatewayConfiguration());

        try {
            $setupIntent = $stripe->setupIntents->retrieve($setupIntentId);
        } catch (StripeError $e) {
            $this->logger->error('Unable to retrieve setup intent on Stripe', ['exception' => $e]);

            return null;
        }

        // Update the account if it is already verified
        if ($paymentSource instanceof BankAccount && 'succeeded' == $setupIntent->status) {
            $this->markBankAccountVerified($paymentSource);

            return null;
        }

        $nextActionType = $setupIntent->next_action?->type; /* @phpstan-ignore-line */

        // ACH microdeposit verification redirect
        if ('verify_with_microdeposits' == $nextActionType) {
            return $setupIntent->next_action?->verify_with_microdeposits->hosted_verification_url; /* @phpstan-ignore-line */
        }

        return null;
    }

    public function markBankAccountVerified(BankAccount $bankAccount): void
    {
        if ($bankAccount->verified) {
            return;
        }

        $bankAccount->verified = true;
        $bankAccount->saveOrFail();

        // Attempt to attach the payment method to a customer.
        // This is needed in the case of a manual deposit verification
        // ACH bank account because the payment method cannot be attached
        // until the setup intent is succeeded.
        try {
            $stripe = $this->getStripe($bankAccount->getMerchantAccount()->toGatewayConfiguration());
            $stripe->paymentMethods->attach((string) $bankAccount->gateway_id, [
                'customer' => $bankAccount->gateway_customer,
            ]);
        } catch (StripeError) {
            // This is a best effort attempt. If it fails then we are going to ignore the error
            // in order to continue processing the transaction.
        }
    }

    /**
     * Creates a payment intent for processing a payment.
     *
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    public function createPaymentIntent(MerchantAccount $merchantAccount, PaymentFlow $paymentFlow, Money $amount, array $documents, array $paymentMethodTypes): string
    {
        $stripe = $this->getStripe($merchantAccount->toGatewayConfiguration());

        $description = GatewayHelper::makeDescription($documents);
        $params = [
            'description' => $description,
            'currency' => $amount->currency,
            'amount' => $amount->amount,
            'payment_method_types' => $paymentMethodTypes,
            'metadata' => [
                'invoiced.payment_flow' => $paymentFlow->identifier,
            ],
            'setup_future_usage' => 'off_session',
        ];

        $customer = $paymentFlow->customer;
        if ($customer?->id) {
            $stripeCustomer = $this->findOrCreateStripeCustomer($customer, $stripe);
            if ($stripeCustomer) {
                $params['customer'] = $stripeCustomer->id;
            }
            $params['metadata']['invoiced.customer'] = (string) $customer->id;

            $level3 = GatewayHelper::makeLevel3($documents, $customer, $amount);
            $params['level3'] = $this->buildStripeLevel3($level3, $amount);
        }

        if (count($documents) > 0) {
            $params['metadata']['invoiced.invoice'] = (string) $documents[0]->id;
        }

        try {
            $setupIntent = $stripe->paymentIntents->create($params);
        } catch (StripeError $e) {
            $this->logger->error('Unable to create payment intent on Stripe', ['exception' => $e]);

            throw new ChargeException($e->getMessage());
        }

        return (string) $setupIntent->client_secret;
    }

    /**
     * Creates a charge from a payment intent that has already been confirmed.
     *
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function chargePaymentIntent(StripeClient $stripe, Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents): ChargeValueObject
    {
        $paymentIntentId = $parameters['payment_intent'];

        $this->gatewayLogger->logJsonRequest([], self::MASKED_REQUEST_PARAMETERS);

        // Look up the payment intent
        try {
            $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId, ['expand' => ['latest_charge']]);
        } catch (StripeError $e) {
            $this->logger->error('Unable to retrieve payment intent on Stripe', ['exception' => $e]);

            throw new ChargeException($this->buildErrorMessage($e));
        }

        // this can be used to log the gateway response
        $this->logGatewayResponse($paymentIntent->getLastResponse());

        $charge = $paymentIntent->latest_charge;
        if (!$charge instanceof StripeCharge) {
            throw new ChargeException('Payment has not been attempted');
        }

        // build Invoiced charge object
        return $this->buildCharge($stripe, $charge, $customer, $account, null, $parameters, $description);
    }

    /**
     * Builds an error message from Stripe.
     */
    private function buildErrorMessage(StripeError $e): string
    {
        // this can be used to log the gateway response
        if ($e instanceof ApiErrorException) {
            $this->gatewayLogger->logStringResponse((string) $e->getHttpBody());
        }

        return $e->getMessage();
    }

    /**
     * Builds a gateway response message from a Stripe API response.
     */
    private function logGatewayResponse(?object $response): void
    {
        if (!($response instanceof StripeApiResponse)) {
            return;
        }

        // find the request ID, useful for troubleshooting
        $requestId = null;
        if (is_array($response->headers)) {
            $requestId = $response->headers['Request-Id'] ?? null;
        }

        $params = [
            'request_id' => $requestId,
            'body' => $response->json,
        ];

        $this->gatewayLogger->logStringResponse((string) json_encode($params));
    }

    /**
     * Builds a transaction status object from a Stripe API response.
     */
    private function buildTransactionStatus(StripeCharge $charge): array
    {
        $status = '';

        switch ($charge->status) {
            case 'succeeded':
                $status = ChargeValueObject::SUCCEEDED;

                break;

            case 'pending':
                $status = ChargeValueObject::PENDING;

                break;

            case 'failed':
                $status = ChargeValueObject::FAILED;

                break;
        }

        return [$status, $charge->failure_message];
    }

    /**
     * Builds a Refund object from a Stripe refund object.
     */
    private function buildRefund(StripeRefund $result, Money $amount): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result->id,
            status: RefundValueObject::SUCCEEDED
        );
    }

    /**
     * Strips all non-alphanumeric characters.
     */
    private function alphanumOnly(?string $input): string
    {
        $input = str_replace(['_', '-'], ' ', (string) $input);

        return (string) preg_replace('/[^A-Za-z0-9\s]/', '', $input);
    }

    private function isPaymentMethod(string $id): bool
    {
        return str_starts_with($id, 'pm_');
    }

    private function isSetupIntent(string $id): bool
    {
        return str_starts_with($id, 'seti_');
    }

    /**
     * Performs a charge through a payment intent.
     *
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function performPaymentIntent(StripeClient $stripe, Customer $customer, MerchantAccount $account, ?PaymentSource $source, Money $amount, array $documents, string $description, array $parameters): ChargeValueObject
    {
        // set up the payment intent
        $paymentIntentRequest = $this->buildPaymentIntentParameters($customer, $source, $amount, $description, $documents, $parameters);

        // retrieve the payment method to determine what type of payment intent to create
        try {
            $paymentMethod = $stripe->paymentMethods->retrieve($paymentIntentRequest['payment_method']);
            $this->logGatewayResponse($paymentMethod->getLastResponse());
            $paymentIntentRequest['payment_method_types'] = [$paymentMethod->type];
            if ($paymentMethod['customer']) {
                $paymentIntentRequest['customer'] = $paymentMethod['customer'];
            }
        } catch (StripeError $e) {
            throw new ChargeException($this->buildErrorMessage($e));
        }

        $this->gatewayLogger->logJsonRequest($paymentIntentRequest, self::MASKED_REQUEST_PARAMETERS);

        // create payment intent on stripe merchant account
        try {
            $paymentIntent = $stripe->paymentIntents->create($paymentIntentRequest);
        } catch (StripeError $e) {
            $failedCharge = null;
            if ($e instanceof ApiErrorException) {
                $failedCharge = $this->buildFailedCharge($stripe, (array) $e->getJsonBody(), $customer, $account, $source, $parameters, $description);
            }

            throw new ChargeException($this->buildErrorMessage($e), $failedCharge);
        }

        // this can be used to log the gateway response
        $this->logGatewayResponse($paymentIntent->getLastResponse());
        // build Invoiced charge object
        $charge = $paymentIntent->charges->data[0];/* @phpstan-ignore-line */

        if ($source === null) {
            $cardData = $charge->toArray();
            $cardData['invoicedStatus'] = [
                'status' => ChargeValueObject::SUCCEEDED,
                'failureMessage' => null,
            ];
            $chargeArray = PaymentFlowReconcileData::fromStripe($cardData);
            $cardValueObject = $chargeArray->toSourceValueObject($customer, $account, false, null);
            $source = $this->sourceReconciler->reconcile($cardValueObject);
        }


        return $this->buildCharge($stripe, $charge, $customer, $account, $source, $parameters, $description);
    }

    /**
     * @param ReceivableDocument[] $documents
     */
    private function buildPaymentIntentParameters(Customer $customer, ?PaymentSource $source, Money $amount, string $description, array $documents, array $parameters): array
    {
        $params = [
            'description' => $description,
            'currency' => $amount->currency,
            'amount' => $amount->amount,
            'metadata' => [
                'invoiced.customer' => (string) $customer->id,
            ],
            'off_session' => true,
            'confirm' => true,
        ];

        if ($source) {
            $params['customer'] = $source->gateway_customer ?? $customer->metadata->stripe_customer_id;
            $params['payment_method'] = (string) $source->gateway_id;
        } else {
            $params['payment_method'] = $parameters['gateway_token'];
        }

        if (isset($parameters['stripe_customer_id'])) {
            $params['customer'] = $parameters['stripe_customer_id'];
        }

        if (count($documents) > 0) {
            $params['metadata']['invoiced.invoice'] = (string) $documents[0]->id;
        }

        if (array_key_exists('capture', $parameters)) {
            $params['capture'] = filter_var($parameters['capture'], FILTER_VALIDATE_BOOLEAN);
        }

        $level3 = GatewayHelper::makeLevel3($documents, $customer, $amount);
        $params['level3'] = $this->buildStripeLevel3($level3, $amount);

        return $params;
    }

    /**
     * Build charge object when it fails on gateway.
     */
    private function buildFailedCharge(StripeClient $stripe, array $result, Customer $customer, MerchantAccount $account, ?PaymentSource $source, array $parameters, string $description): ?ChargeValueObject
    {
        if (!$result) {
            return null;
        }

        // do not build a charge object if Stripe did not produce one
        // i.e. an invalid request parameter
        $error = $result['error'];
        if (!isset($error['charge'])) {
            return null;
        }

        // lookup the charge on Stripe
        try {
            $stripeCharge = $stripe->charges->retrieve($error['charge']);
        } catch (ApiErrorException) {
            // do nothing here since the charge was already a failure
            return null;
        }

        return $this->buildCharge($stripe, $stripeCharge, $customer, $account, $source, $parameters, $description);
    }

    /**
     * Builds a Charge object from a Stripe charge.
     */
    private function buildCharge(StripeClient $stripe, StripeCharge $stripeCharge, Customer $customer, MerchantAccount $account, ?PaymentSource $source, array $parameters, string $description): ChargeValueObject
    {
        $message = $stripeCharge->failure_message;
        if (!$message) {
            $message = $stripeCharge->outcome?->seller_message; /* @phpstan-ignore-line */
        }

        // When a bank account is used to make a successful payment
        // then we can assume it has been verified.
        if ($source instanceof BankAccount && ChargeValueObject::FAILED != $stripeCharge->status) {
            $source->verified = true;
            $source->saveOrFail();
        }

        if (!$source) {
            // Build payment source from Source on charge
            $sourceValueObject = $stripeCharge->source ? $this->getSource($account, $customer, $stripeCharge->source, $parameters, false) : null;

            // Build payment source from PaymentMethod on charge
            if (!$sourceValueObject && $paymentMethodId = $stripeCharge->payment_method) {
                try {
                    $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethodId);
                    $sourceValueObject = $this->getSourcePaymentMethod($account, $customer, $paymentMethod, null, $parameters, false);
                } catch (StripeError) {
                    // do nothing
                }
            }

            if ($sourceValueObject) {
                try {
                    $source = $this->sourceReconciler->reconcile($sourceValueObject);
                } catch (ReconciliationException) {
                    // do nothing
                }
            }
        }

        return new ChargeValueObject(
            customer: $customer,
            amount: new Money($stripeCharge->currency, $stripeCharge->amount),
            gateway: self::ID,
            gatewayId: $stripeCharge->id,
            method: '',
            status: $stripeCharge->status,
            merchantAccount: $account,
            source: $source,
            description: $description,
            timestamp: $stripeCharge->created,
            failureReason: $message,
        );
    }

    private function buildStripeLevel3(Level3Data $level3, Money $transactionTotal): array
    {
        $currency = $transactionTotal->currency;
        $total = $level3->shipping;
        $subtotal = $transactionTotal->subtract($level3->salesTax)->subtract($level3->shipping);
        $shipping = $level3->shipping;

        $lineItems = [];
        foreach ($level3->lineItems as $lineItem) {
            // Stripe does not allow for a decimal to be used for quantity
            // recalculate total using an integer quantity. Any extra amount
            // will be added with the adjustment line item.
            $quantity = (int) floor($lineItem->quantity);
            $discount = $lineItem->discount;
            $unitCost = $lineItem->unitCost;
            $lineTotal = Money::fromDecimal($currency, $quantity * $unitCost->toDecimal())->subtract($lineItem->discount);

            // prorate tax across line items, as required for Level 3
            $prorationFactor = !$subtotal->isZero() ? $lineTotal->toDecimal() / $subtotal->toDecimal() : 1;
            $lineItemTax = Money::fromDecimal($currency, $level3->salesTax->toDecimal() * $prorationFactor);
            $total = $total->add($lineTotal->add($lineItemTax));

            $lineItems[] = [
                'product_code' => substr($this->alphanumOnly($lineItem->productCode), 0, 12),
                'product_description' => substr($this->alphanumOnly($lineItem->description), 0, 26),
                'unit_cost' => $unitCost->amount,
                'quantity' => $quantity,
                'tax_amount' => $lineItemTax->amount,
                'discount_amount' => $discount->amount,
            ];
        }

        // The line items must always add up to the order total
        // in order to pass Stripe's validation. If they do not
        // add up to the order total then an adjustment line item
        // is added. This scenario can happen when there is a
        // convenience fee or partial payment.
        $difference = $transactionTotal->subtract($total);
        if (!$difference->isZero()) {
            $lineItems[] = [
                'product_code' => 'Adjustment',
                'product_description' => 'Adjustment',
                'unit_cost' => $difference->amount,
                'quantity' => 1,
                'tax_amount' => 0,
                'discount_amount' => 0,
            ];
        }

        // Stripe does not allow sending any monetary values with a negative amount.
        // When this happens we must fallback to Level 2 data in order for the
        // request to be accepted.
        // fallback apply when count of line items is 0
        $level2Fallback = 0 === count($lineItems);
        foreach ($lineItems as $lineItem) {
            if ($lineItem['unit_cost'] < 0 || $lineItem['quantity'] < 0 || $lineItem['tax_amount'] < 0 || $lineItem['discount_amount'] < 0) {
                $level2Fallback = true;
                break;
            }
        }

        if ($level2Fallback) {
            // We have to ensure that the subtotal + tax never exceeds the payment amount.
            // This would happen in a partial payment scenario.
            $subtotal = $subtotal->max(new Money($currency, 0));
            $taxAmount = $transactionTotal->subtract($subtotal);
            $shipping = new Money($currency, 0);

            $lineItems = [
                [
                    'product_code' => 'unknown',
                    'product_description' => 'Order Summary',
                    'unit_cost' => $subtotal->amount,
                    'quantity' => 1,
                    'tax_amount' => $taxAmount->amount,
                    'discount_amount' => 0,
                ],
            ];
        }

        // Only pass in zip codes if they meet the U.S. format.
        // Stripe will reject any non-US zip code.
        $shipToZipCode = null;
        if ('US' == $level3->shipTo['country'] && $this->isUsZipCode((string) $level3->shipTo['postal_code'])) {
            $shipToZipCode = $level3->shipTo['postal_code'];
        }

        $shipFromZipCode = null;
        if ($this->isUsZipCode($level3->merchantPostalCode)) {
            $shipFromZipCode = $level3->merchantPostalCode;
        }

        $merchantReference = substr($this->alphanumOnly($level3->poNumber), 0, 25);

        return array_filter([
            'merchant_reference' => $merchantReference ?: 'Unknown',
            'shipping_address_zip' => $shipToZipCode,
            'shipping_from_zip' => $shipFromZipCode,
            'shipping_amount' => $shipping->amount,
            'line_items' => $lineItems,
        ]);
    }

    private function isUsZipCode(string $input): bool
    {
        return (bool) preg_match('/^\d{5}(?:[-\s]\d{4})?$/', $input);
    }

    /**
     * Performs a charge.
     *
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function performStripeCharge(StripeClient $stripe, Customer $customer, MerchantAccount $account, ?PaymentSource $source, Money $amount, array $documents, array $parameters, string $description): ChargeValueObject
    {
        // set up the charge
        $params = $this->buildChargeParameters($customer, $source, $amount, $description, $documents, $parameters);

        $this->gatewayLogger->logJsonRequest($params, self::MASKED_REQUEST_PARAMETERS);

        // perform charge on stripe merchant account
        try {
            $stripeCharge = $stripe->charges->create($params);
        } catch (StripeError $e) {
            $failedCharge = null;
            if ($e instanceof ApiErrorException) {
                $failedCharge = $this->buildFailedCharge($stripe, (array) $e->getJsonBody(), $customer, $account, $source, $parameters, $description);
            }

            throw new ChargeException($this->buildErrorMessage($e), $failedCharge);
        }

        // this can be used to log the gateway response
        $this->logGatewayResponse($stripeCharge->getLastResponse());

        // build Invoiced charge object
        return $this->buildCharge($stripe, $stripeCharge, $customer, $account, $source, $parameters, $description);
    }

    /**
     * @param ReceivableDocument[] $documents
     */
    private function buildChargeParameters(Customer $customer, ?PaymentSource $source, Money $amount, string $description, array $documents, array $parameters): array
    {
        $params = [
            'description' => $description,
            'currency' => $amount->currency,
            'amount' => $amount->amount,
            'metadata' => [
                'invoiced.customer' => (string) $customer->id,
            ],
        ];

        if ($source) {
            $params['customer'] = $source->gateway_customer ?? $customer->metadata->stripe_customer_id;
            $params['source'] = $source->gateway_id;
        } else {
            $params['source'] = $parameters['gateway_token'];
        }

        if (isset($parameters['stripe_customer_id'])) {
            $params['customer'] = $parameters['stripe_customer_id'];
        }

        if (count($documents) > 0) {
            $params['metadata']['invoiced.invoice'] = (string) $documents[0]->id;
        }

        if (array_key_exists('capture', $parameters)) {
            $params['capture'] = filter_var($parameters['capture'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($source instanceof Card) {
            $level3 = GatewayHelper::makeLevel3($documents, $customer, $amount);
            $params['level3'] = $this->buildStripeLevel3($level3, $amount);
        }

        return $params;
    }

    private function vaultBankAccount(StripeClient $stripe, Customer $customer, MerchantAccount $account, array $parameters): BankAccountValueObject
    {
        // use referenced stripe customer
        $customerId = $parameters['stripe_customer_id'];

        $params = [
            'source' => $this->buildStripeBankAccount($parameters),
        ];

        $this->gatewayLogger->logJsonRequest($params, self::MASKED_REQUEST_PARAMETERS);

        try {
            /** @var Source $stripeSource */
            $stripeSource = $stripe->customers->createSource($customerId, $params);
        } catch (ApiErrorException $e) {
            throw new PaymentSourceException($this->buildErrorMessage($e));
        }

        // this can be used to log the gateway response
        $this->logGatewayResponse($stripeSource->getLastResponse());

        // Look up the bank name
        $routingNumber = $this->routingNumberLookup->lookup($parameters['routing_number']);

        return new BankAccountValueObject(
            customer: $customer,
            gateway: $account->gateway,
            gatewayId: $stripeSource->id,
            gatewayCustomer: $customerId,
            merchantAccount: $account,
            chargeable: true,
            receiptEmail: $parameters['receipt_email'] ?? null,
            bankName: $routingNumber?->bank_name ?: 'Unknown',
            routingNumber: $parameters['routing_number'],
            accountNumber: $parameters['account_number'],
            last4: substr($parameters['account_number'] ?? '', -4, 4) ?: '0000',
            currency: 'usd',
            country: 'US',
            accountHolderName: $parameters['account_holder_name'],
            accountHolderType: $parameters['account_holder_type'],
            type: $parameters['type'] ?? null,
            verified: self::STATUS_VERIFIED === $stripeSource->status,
        );
    }

    /**
     * Builds a bank account for use on Stripe.
     */
    private function buildStripeBankAccount(array $parameters): array
    {
        return [
            'object' => 'bank_account',
            'account_number' => $parameters['account_number'],
            'routing_number' => $parameters['routing_number'],
            'country' => 'US',
            'currency' => 'usd',
            'account_holder_name' => $parameters['account_holder_name'],
            'account_holder_type' => $parameters['account_holder_type'],
        ];
    }

    /**
     * Charge a gateway token.
     *
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function chargeGatewayToken(StripeClient $stripe, Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents): ChargeValueObject
    {
        // When the gateway token is a payment method then we must
        // use a payment intent.
        $gatewayToken = $parameters['gateway_token'];
        if ($this->isPaymentMethod($gatewayToken)) {
            return $this->performPaymentIntent($stripe, $customer, $account, null, $amount, $documents, $description, $parameters);
        }

        return $this->performStripeCharge($stripe, $customer, $account, null, $amount, $documents, $parameters, $description);
    }

    /**
     * Vaults payment information given a gateway token.
     *
     * @throws PaymentSourceException when the operation fails
     */
    private function vaultGatewayToken(StripeClient $stripe, Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        // Use the Stripe Setup Intents API
        $token = $parameters['gateway_token'];
        if ($this->isSetupIntent($token)) {
            try {
                $setupIntent = $stripe->setupIntents->retrieve($token, ['expand' => ['payment_method']]);
                /** @var PaymentMethod $paymentMethod */
                $paymentMethod = $setupIntent->payment_method;
            } catch (ApiErrorException $e) {
                throw new PaymentSourceException($this->buildErrorMessage($e));
            }

            // this can be used to log the gateway response
            $this->logGatewayResponse($setupIntent->getLastResponse());

            // Attach the payment method to the customer if the Setup Intent did not already do this
            if (!$setupIntent->customer && 'succeeded' == $setupIntent->status) {
                $paymentMethod = $this->attachPaymentMethod($stripe, $parameters['stripe_customer_id'], $paymentMethod->id);
            }

            $source = $this->getSourcePaymentMethod($account, $customer, $paymentMethod, $setupIntent, $parameters, true);
            if (!$source) {
                throw new PaymentSourceException('Payment method type "'.$paymentMethod->type.'" not recognized');
            }

            return $source;
        }

        // Use the Stripe Payment Methods API
        if ($this->isPaymentMethod($token)) {
            $paymentMethod = $this->attachPaymentMethod($stripe, $parameters['stripe_customer_id'], $token);

            $source = $this->getSourcePaymentMethod($account, $customer, $paymentMethod, null, $parameters, true);
            if (!$source) {
                throw new PaymentSourceException('Payment method type "'.$paymentMethod->type.'" not recognized');
            }

            return $source;
        }

        // Use the legacy Stripe Sources API
        $params = [
            'source' => $token,
        ];

        $this->gatewayLogger->logJsonRequest($params, self::MASKED_REQUEST_PARAMETERS);

        try {
            $stripeSource = $stripe->customers->createSource($parameters['stripe_customer_id'], $params);
        } catch (StripeError $e) {
            throw new PaymentSourceException($this->buildErrorMessage($e));
        }

        // this can be used to log the gateway response
        $this->logGatewayResponse($stripeSource->getLastResponse());

        $source = $this->getSource($account, $customer, $stripeSource, $parameters, true);
        if (!$source) {
            throw new PaymentSourceException('Source type not recognized');
        }

        return $source;
    }

    private function attachPaymentMethod(StripeClient $stripe, string $customerId, string $paymentMethodId): PaymentMethod
    {
        $params = [
            'customer' => $customerId,
        ];

        $this->gatewayLogger->logJsonRequest($params, self::MASKED_REQUEST_PARAMETERS);

        try {
            $paymentMethod = $stripe->paymentMethods->attach($paymentMethodId, $params);
        } catch (ApiErrorException $e) {
            throw new PaymentSourceException($this->buildErrorMessage($e));
        }

        // this can be used to log the gateway response
        $this->logGatewayResponse($paymentMethod->getLastResponse());

        return $paymentMethod;
    }

    /**
     * Build Source object if it's a card or bank account from the charge payment method.
     */
    private function getSourcePaymentMethod(MerchantAccount $account, Customer $customer, PaymentMethod $paymentMethod, ?object $setupIntent, array $parameters, bool $vault): ?SourceValueObject
    {
        if ('card' == $paymentMethod->type && $card = $paymentMethod->card) {
            return new CardValueObject(
                customer: $customer,
                gateway: self::ID,
                gatewayId: $paymentMethod->id,
                gatewayCustomer: $paymentMethod->customer,
                gatewaySetupIntent: $setupIntent?->id,
                merchantAccount: $account,
                chargeable: $vault,
                receiptEmail: $parameters['receipt_email'] ?? null,
                brand: $card->brand ?? 'Unknown',
                funding: strtolower($card->funding ?? 'unknown'),
                last4: $card->last4 ?? '0000',
                expMonth: $card->exp_month, /* @phpstan-ignore-line */
                expYear: $card->exp_year, /* @phpstan-ignore-line */
                country: $card->country, /* @phpstan-ignore-line */
            );
        }

        if ('us_bank_account' == $paymentMethod->type && $bankAcount = $paymentMethod->us_bank_account) {
            return new BankAccountValueObject(
                customer: $customer,
                gateway: self::ID,
                gatewayId: $paymentMethod->id,
                gatewayCustomer: $paymentMethod->customer,
                gatewaySetupIntent: $setupIntent?->id,
                merchantAccount: $account,
                chargeable: $vault,
                receiptEmail: $parameters['receipt_email'] ?? null,
                bankName: $bankAcount->bank_name ?: 'Unknown', /* @phpstan-ignore-line */
                routingNumber: $bankAcount->routing_number, /* @phpstan-ignore-line */
                last4: $bankAcount->last4 ?: '0000', /* @phpstan-ignore-line */
                currency: 'usd',
                country: 'US',
                accountHolderName: $paymentMethod->billing_details->name, /* @phpstan-ignore-line */
                accountHolderType: $bankAcount->account_holder_type, /* @phpstan-ignore-line */
                type: $bankAcount->account_type, /* @phpstan-ignore-line */
                verified: !$setupIntent || 'succeeded' == $setupIntent->status,
            );
        }

        return null;
    }

    /**
     * Build Source object if it's a card or bank account from the charge source.
     */
    private function getSource(MerchantAccount $account, Customer $customer, object $stripeSource, array $parameters, bool $vault): ?SourceValueObject
    {
        if ('card' == $stripeSource->object) { /* @phpstan-ignore-line */
            return new CardValueObject(
                customer: $customer,
                gateway: self::ID,
                gatewayId: $stripeSource->id,
                gatewayCustomer: $stripeSource->customer,
                merchantAccount: $account,
                chargeable: $vault,
                receiptEmail: $parameters['receipt_email'] ?? null,
                brand: $stripeSource->brand ?? 'Unknown',
                funding: strtolower($stripeSource->funding ?? 'unknown'),
                last4: $stripeSource->last4 ?? '0000',
                expMonth: $stripeSource->exp_month,
                expYear: $stripeSource->exp_year,
                country: $stripeSource->country ?? null,
            );
        }

        if ('bank_account' == $stripeSource->object) { /* @phpstan-ignore-line */
            return new BankAccountValueObject(
                customer: $customer,
                gateway: self::ID,
                gatewayId: $stripeSource->id,
                gatewayCustomer: $stripeSource->customer,
                merchantAccount: $account,
                chargeable: $vault,
                receiptEmail: $parameters['receipt_email'] ?? null,
                bankName: $stripeSource->bank_name ?: 'Unknown',
                routingNumber: $stripeSource->routing_number,
                last4: $stripeSource->last4 ?: '0000',
                currency: $stripeSource->currency,
                country: $stripeSource->country,
                accountHolderName: $stripeSource->account_holder_name,
                accountHolderType: $stripeSource->account_holder_type,
                verified: self::STATUS_VERIFIED === $stripeSource->status,
            );
        }

        return null;
    }
}
