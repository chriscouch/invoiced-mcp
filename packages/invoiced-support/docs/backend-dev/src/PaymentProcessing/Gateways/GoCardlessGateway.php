<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\GoCardless\GoCardlessApi;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Exceptions\VoidException;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Interfaces\VoidInterface;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use Carbon\CarbonImmutable;
use GoCardlessPro\Client;
use GoCardlessPro\Core\Exception\ApiException;
use GoCardlessPro\Core\ListResponse;
use GoCardlessPro\Resources\Payment;
use GoCardlessPro\Resources\Refund as GoCardlessRefund;
use stdClass;

/**
 * Customizations needed to make the GoCardless gateway work.
 */
class GoCardlessGateway implements PaymentGatewayInterface, PaymentSourceVaultInterface, RefundInterface, VoidInterface, TransactionStatusInterface
{
    const ID = 'gocardless';

    private const STATUS_REASONS = [
        'pending_customer_approval' => 'We’re waiting for the customer to approve this payment',
        'pending_submission' => 'The payment has been created, but not yet submitted to the banks',
        'submitted' => 'The payment has been submitted to the banks',
        'confirmed' => 'The payment has been confirmed as collected',
        'paid_out' => 'The payment has been included in a payout',
        'cancelled' => 'The payment has been cancelled',
        'customer_approval_denied' => 'The customer has denied approval for the payment. You should contact the customer directly',
        'failed' => 'The payment failed to be processed. Note that payments can fail after being confirmed if the failure message is sent late by the banks.',
        'charged_back' => 'The payment has been charged back',
    ];

    public static function getId(): string
    {
        return self::ID;
    }

    public function __construct(
        private GatewayLogger $gatewayLogger,
    ) {
    }

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->access_token)) {
            throw new InvalidGatewayConfigurationException('Missing GoCardless access token!');
        }

        if (!isset($gatewayConfiguration->credentials->environment)) {
            throw new InvalidGatewayConfigurationException('Missing GoCardless environment!');
        }
    }

    //
    // Payment Sources
    //

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        if (!isset($parameters['gateway_token'])) {
            throw new PaymentSourceException('Missing `gateway_token` parameter');
        }

        $params = ['session_token' => $customer->client_id];

        // There are no sensitive parameters since bank account information is collected on a GoCardless hosted page
        $this->gatewayLogger->logJsonRequest($params, []);

        $gatewayConfiguration = $account->toGatewayConfiguration();
        $client = $this->getClient($gatewayConfiguration);

        try {
            $redirectFlow = $client->redirectFlows()
                ->complete($parameters['gateway_token'], ['params' => $params]);
        } catch (ApiException $e) {
            if ($apiResponse = $e->getApiResponse()) {
                $this->gatewayLogger->logJsonResponse($apiResponse->body);
            }

            throw new PaymentSourceException($e->getMessage());
        }

        if ($apiResponse = $redirectFlow->api_response) {
            $this->gatewayLogger->logJsonResponse($apiResponse->body);
        }

        try {
            $mandate = $client->mandates()
                ->get($redirectFlow->links->mandate);
        } catch (ApiException $e) {
            if ($apiResponse = $e->getApiResponse()) {
                $this->gatewayLogger->logJsonResponse($apiResponse->body);
            }

            throw new PaymentSourceException($e->getMessage());
        }

        try {
            $customerBankAccount = $client->customerBankAccounts()
                ->get($redirectFlow->links->customer_bank_account);
        } catch (ApiException $e) {
            if ($apiResponse = $e->getApiResponse()) {
                $this->gatewayLogger->logJsonResponse($apiResponse->body);
            }

            throw new PaymentSourceException($e->getMessage());
        }

        return new BankAccountValueObject(
            customer: $customer,
            gateway: self::ID,
            gatewayId: $redirectFlow->links->mandate,
            merchantAccount: $account,
            chargeable: true,
            receiptEmail: $parameters['receipt_email'] ?? null,
            bankName: $customerBankAccount->bank_name,
            last4: $customerBankAccount->account_number_ending,
            currency: strtolower($customerBankAccount->currency),
            country: $customerBankAccount->country_code,
            verified: 'active' == $mandate->status,
        );
    }

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $customer = $source->customer;
        $params = [
            'amount' => $amount->amount,
            'currency' => strtoupper($amount->currency),
            'description' => $description,
            'links' => [
                'mandate' => $source->gateway_id,
            ],
            'metadata' => [
                'invoiced_customer_id' => (string) $customer->id,
                'customer_account_number' => $customer->number,
            ],
        ];

        if (count($documents) > 0) {
            $params['metadata']['invoiced_invoice_id'] = (string) $documents[0]->id;
        }

        // There are no sensitive parameters since bank account information is collected on a GoCardless hosted page
        $this->gatewayLogger->logJsonRequest($params, []);

        try {
            $gatewayConfiguration = $source->getMerchantAccount()->toGatewayConfiguration();
            $payment = $this->getClient($gatewayConfiguration)
                ->payments()
                ->create(['params' => $params]);
        } catch (ApiException $e) {
            if ($apiResponse = $e->getApiResponse()) {
                $this->gatewayLogger->logJsonResponse($apiResponse->body);
            }

            throw new ChargeException($e->getMessage());
        }

        if ($apiResponse = $payment->api_response) {
            $this->gatewayLogger->logJsonResponse($apiResponse->body);
        }

        return $this->buildCharge($payment, $source, $description);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        try {
            $mandate = $this->getClient($account->toGatewayConfiguration())
                ->mandates()
                ->cancel((string) $source->gateway_id);
        } catch (ApiException $e) {
            if ($apiResponse = $e->getApiResponse()) {
                $this->gatewayLogger->logJsonResponse($apiResponse->body);
            }

            throw new PaymentSourceException($e->getMessage());
        }

        if ($apiResponse = $mandate->api_response) {
            $this->gatewayLogger->logJsonResponse($apiResponse->body);
        }
    }

    //
    // Refunds
    //

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $client = $this->getClient($gatewayConfiguration);

        $totalAmountConfirmation = $amount->amount;
        do {
            /** @var ListResponse $result */
            $result = $client->refunds()
                ->list([
                    'params' => ['payment' => $chargeId],
                ]);
            foreach ($result->records as $refund) {
                $totalAmountConfirmation += $refund->amount;
            }

            $after = $result->after;
        } while ($after);

        $params = [
            'amount' => $amount->amount,
            'total_amount_confirmation' => $totalAmountConfirmation,
            'links' => ['payment' => $chargeId],
        ];

        // There are no sensitive parameters since bank account information is collected on a GoCardless hosted page
        $this->gatewayLogger->logJsonRequest($params, []);

        try {
            $refund = $client->refunds()
                ->create([
                    'params' => $params,
                ]);
        } catch (ApiException $e) {
            if ($apiResponse = $e->getApiResponse()) {
                $this->gatewayLogger->logJsonResponse($apiResponse->body);
            }

            throw new RefundException($e->getMessage());
        }

        return $this->buildRefund($refund, $amount);
    }

    public function void(MerchantAccount $merchantAccount, string $chargeId): void
    {
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();

        try {
            $this->getClient($gatewayConfiguration)
                ->payments()
                ->cancel($chargeId);
        } catch (ApiException $e) {
            if ($apiResponse = $e->getApiResponse()) {
                $this->gatewayLogger->logJsonResponse($apiResponse->body);
            }

            throw new VoidException($e->getMessage());
        }
    }

    //
    // Transaction Status
    //

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        $chargeId = $charge->gateway_id;
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        try {
            $payment = $this->getClient($gatewayConfiguration)->payments()->get($chargeId);
        } catch (ApiException $e) {
            if ($apiResponse = $e->getApiResponse()) {
                $this->gatewayLogger->logJsonResponse($apiResponse->body);
            }

            throw new TransactionStatusException($e->getMessage());
        }

        if ($apiResponse = $payment->api_response) {
            $this->gatewayLogger->logJsonResponse($apiResponse->body);
        }

        return $this->determineStatus($payment);
    }

    //
    // Helpers
    //

    /**
     * Builds the GoCardless client.
     */
    private function getClient(PaymentGatewayConfiguration $gatewayConfiguration): Client
    {
        return new Client([
            'access_token' => $gatewayConfiguration->credentials->access_token,
            'environment' => $gatewayConfiguration->credentials->environment,
        ]);
    }

    /**
     * @throws ApiException
     */
    public function makeRedirectFlow(MerchantAccount $merchantAccount, Customer $customer, string $reason): string
    {
        $api = new GoCardlessApi();
        $client = $api->getClient($merchantAccount);

        $company = $merchantAccount->tenant();
        $redirectUrl = $company->url.'/newDirectDebitMandate/'.$customer->client_id.'/complete';
        $prefilledCustomer = [
            'email' => $customer->emailAddress(),
            'address_line1' => $customer->address1,
            'address_line2' => $customer->address2,
            'city' => $customer->city,
            'region' => $customer->state,
            'postal_code' => $customer->postal_code,
            'country_code' => $customer->country,
            'language' => $customer->language,
        ];

        // prefilled customer can only have strings
        foreach ($prefilledCustomer as $k => $value) {
            if (!$value) {
                unset($prefilledCustomer[$k]);
            }
        }

        if ('company' == $customer->type) {
            $prefilledCustomer['company_name'] = $customer->name;
        } else {
            $names = explode(' ', $customer->name);
            $prefilledCustomer['given_name'] = $names[0];
            if (count($names) > 1) {
                $prefilledCustomer['family_name'] = join(' ', array_splice($names, 1));
            }
        }

        try {
            /** @var stdClass $redirectFlow */
            $redirectFlow = $client->redirectFlows()
                ->create([
                    'params' => [
                        'description' => $reason,
                        'session_token' => $customer->client_id,
                        'success_redirect_url' => $redirectUrl,
                        'prefilled_customer' => $prefilledCustomer,
                    ],
                ]);
        } catch (ApiException $e) {
            if ($apiResponse = $e->getApiResponse()) {
                $this->gatewayLogger->logJsonResponse($apiResponse->body);
            }

            throw $e;
        }

        if ($apiResponse = $redirectFlow->api_response) {
            $this->gatewayLogger->logJsonResponse($apiResponse->body);
        }

        return $redirectFlow->redirect_url;
    }

    /**
     * Determines the status of a payment from GoCardless.
     *
     * @return array [status, message]
     */
    private function determineStatus(Payment $payment): array
    {
        $description = self::STATUS_REASONS[$payment->status];
        if (in_array($payment->status, ['pending_customer_approval', 'pending_submission', 'submitted'])) {
            return [ChargeValueObject::PENDING, $description];
        }

        if (in_array($payment->status, ['confirmed', 'paid_out'])) {
            return [ChargeValueObject::SUCCEEDED, $description];
        }

        return [ChargeValueObject::FAILED, $description];
    }

    /**
     * Builds a Refund object from a GoCardless refund object.
     */
    private function buildRefund(GoCardlessRefund $refund, Money $amount): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            timestamp: $this->parseTimestamp($refund->created_at),
            gateway: self::ID,
            gatewayId: $refund->id,
            status: RefundValueObject::SUCCEEDED,
        );
    }

    /**
     * Parses an ISO-8601 timestamp from GoCardless,
     *   i.e. 2018-06-14T20:58:23.224Z.
     */
    private function parseTimestamp(string $timestamp): int
    {
        $datetime = CarbonImmutable::createFromFormat('Y-m-d\TH:i:s+', $timestamp);
        if (!$datetime) {
            return time();
        }

        return $datetime->getTimestamp();
    }

    /**
     * Builds a Charge object from a GoCardless transaction response.
     */
    private function buildCharge(Payment $payment, PaymentSource $source, string $description): ChargeValueObject
    {
        $total = new Money($payment->currency, $payment->amount);

        [$status, $message] = $this->determineStatus($payment);

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $total,
            gateway: self::ID,
            gatewayId: $payment->id,
            method: PaymentMethod::DIRECT_DEBIT,
            status: $status,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            timestamp: $this->parseTimestamp($payment->created_at),
            failureReason: $message,
        );
    }
}
