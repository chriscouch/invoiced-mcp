<?php

namespace App\PaymentProcessing\Gateways;

use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Exceptions\TestGatewayCredentialsException;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TestCredentialsInterface;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use Braintree\Configuration as BraintreeConfiguration;
use Braintree\Exception as BraintreeException;
use Braintree\Exception\Authentication as BraintreeAuthentication;
use Braintree\Exception\NotFound as BraintreeNotFoundException;
use Braintree\PaymentMethod as BraintreePaymentMethod;
use Braintree\Plan as BraintreePlan;
use Braintree\Result\Error as BraintreeError;
use Braintree\Result\Successful as BraintreeResult;
use Braintree\Transaction as BraintreeTransaction;
use Braintree\Xml;

class BraintreeGateway extends AbstractGateway implements RefundInterface, TestCredentialsInterface
{
    const ID = 'braintree';

    private const STATUS_SETTLED = 'settled';
    private const STATUS_SETTLING = 'settling';

    private const PARTNER_ID = 'Invoiced_SP';

    private const MASK_REGEXES = [
        '/\<number\>(.*)\<\/number\>/',
        '/\<cvv\>(.*)\<\/cvv\>/',
    ];

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->merchant_id)) {
            throw new InvalidGatewayConfigurationException('Missing Braintree merchant ID!');
        }

        if (!isset($gatewayConfiguration->credentials->merchant_account_id) && !isset($gatewayConfiguration->credentials->merchant_account_ids)) {
            throw new InvalidGatewayConfigurationException('Missing Braintree merchant account ID!');
        }

        if (!isset($gatewayConfiguration->credentials->public_key)) {
            throw new InvalidGatewayConfigurationException('Missing Braintree public key!');
        }

        if (!isset($gatewayConfiguration->credentials->private_key)) {
            throw new InvalidGatewayConfigurationException('Missing Braintree private key!');
        }
    }

    //
    // Payment Sources
    //

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $account = $source->getMerchantAccount();
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $this->configure($gatewayConfiguration);

        $params = [
            'amount' => (string) $amount->toDecimal(),
            'paymentMethodToken' => $source->gateway_id,
            'channel' => self::PARTNER_ID,
            'options' => [
                'submitForSettlement' => true,
            ],
        ];

        // pass in extra transaction metadata
        if (count($documents) > 0) {
            $params['orderId'] = (string) $documents[0]->id;
        }

        // set the merchant account ID
        if (property_exists($gatewayConfiguration->credentials, 'merchant_account_id')) {
            $params['merchantAccountId'] = $gatewayConfiguration->credentials->merchant_account_id;
        } elseif (property_exists($gatewayConfiguration->credentials, 'merchant_account_ids')) {
            $params['merchantAccountId'] = $this->getMerchantAccountId($gatewayConfiguration->credentials->merchant_account_ids, $amount->currency);
        }

        $this->logRequest(['transaction' => array_merge(['type' => BraintreeTransaction::SALE], $params)]);

        try {
            $result = BraintreeTransaction::sale($params);
        } catch (BraintreeException $e) {
            throw new ChargeException($this->buildExceptionMessage($e));
        }

        // handle failures
        if (!$result->success) {
            $this->logError($result);

            // build a failed charge
            $charge = $this->buildFailedCharge($result, $source, $amount, $description);

            throw new ChargeException($this->buildErrorMessage($result), $charge);
        }

        // log it
        $this->logSuccess($result);

        return $this->buildCharge($result, $source, $amount, $description);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        $this->configure($account->toGatewayConfiguration());

        try {
            $result = BraintreePaymentMethod::delete($source->gateway_id);
        } catch (BraintreeException $e) {
            throw new PaymentSourceException($this->buildExceptionMessage($e));
        }

        // handle failures
        if (!$result->success) {
            $this->logError($result);

            throw new PaymentSourceException($this->buildErrorMessage($result));
        }

        // log it
        $this->logSuccess($result);
    }

    //
    // Refunds
    //

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $this->configure($gatewayConfiguration);

        // Look up transaction by ID to dermine settlement status.
        try {
            $transaction = BraintreeTransaction::find($chargeId);
        } catch (BraintreeNotFoundException $e) {
            throw new RefundException('No such transaction: '.$chargeId);
        } catch (BraintreeException $e) {
            throw new RefundException($this->buildExceptionMessage($e));
        }

        $settled = in_array($transaction->status, [self::STATUS_SETTLING, self::STATUS_SETTLED]);

        // If it's already been settled then we need to refund it.
        // If it has not been settled yet then we need to void it.
        try {
            if ($settled) {
                $this->logRequest(['transaction' => ['amount' => (string) $amount->toDecimal()]]);

                $result = BraintreeTransaction::refund($chargeId, (string) $amount->toDecimal());
            } else {
                // When voiding a transaction a partial refund
                // is not possible. If a partial refund is requested
                // then we are going to block it to prevent a merchant
                // from sending an unexpected larger amount of money back
                // to the customer.
                $transactionAmount = Money::fromDecimal($transaction->currencyIsoCode, $transaction->amount);
                if ($transactionAmount->greaterThan($amount)) {
                    throw new RefundException('A partial refund cannot be issued for this transaction because it has not settled yet. You may issue a full refund or try again once the transaction has been settled.');
                }

                $result = BraintreeTransaction::void($chargeId);
            }
        } catch (BraintreeException $e) {
            throw new RefundException($this->buildExceptionMessage($e));
        }

        // handle failures
        if (!$result->success) {
            $this->logError($result);

            throw new RefundException($this->buildErrorMessage($result));
        }

        // log it
        $this->logSuccess($result);

        // parse the result
        return $this->buildRefund($result, $amount);
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        $this->configure($gatewayConfiguration);

        try {
            $result = BraintreePlan::all();
        } catch (BraintreeAuthentication $e) {
            throw new TestGatewayCredentialsException('Invalid Api Keys');
        } catch (BraintreeException $e) {
            throw new TestGatewayCredentialsException($this->buildExceptionMessage($e));
        }

        // log it
        $this->gatewayLogger->logStringResponse((string) json_encode($result));
    }

    //
    // Helpers
    //

    /**
     * Configures the Braintree client library.
     */
    private function configure(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (isset($gatewayConfiguration->credentials->test_mode) && $gatewayConfiguration->credentials->test_mode) {
            $environment = 'sandbox';
        } else {
            $environment = 'production';
        }

        BraintreeConfiguration::environment($environment);
        BraintreeConfiguration::merchantId($gatewayConfiguration->credentials->merchant_id);
        BraintreeConfiguration::publicKey($gatewayConfiguration->credentials->public_key);
        BraintreeConfiguration::privateKey($gatewayConfiguration->credentials->private_key);
    }

    private function buildExceptionMessage(BraintreeException $e): string
    {
        if ($message = $e->getMessage()) {
            return $message;
        }

        return 'An unknown error has occurred';
    }

    /**
     * Builds an error message from a Braintree transaction
     * with validation errors.
     */
    private function buildErrorMessage(BraintreeError $result): string
    {
        // check if the transaction was declined by the processor
        $transaction = $result->transaction; /* @phpstan-ignore-line */
        if ($transaction instanceof BraintreeTransaction) {
            // We only want to return the processor response if it is a decline
            // which is a 2000 or 3000-class code. The 1000 class code indicates
            // approval and that the transaction was declined by the gateway.
            // See: https://developers.braintreepayments.com/reference/general/processor-responses/authorization-responses
            if ($reason = $transaction->processorResponseText && $transaction->processorResponseCode >= 2000) {
                return 'The transaction was declined by the processor with reason: '.$reason;
            }
        }

        // otherwise look for validation errors
        $message = [];
        foreach ($result->errors->deepAll() as $error) {
            $message[] = $error->message;
        }

        $message = implode(', ', $message);
        if (!$message) {
            return 'An unknown error has occurred';
        }

        return $message;
    }

    /**
     * Logs a Braintree successful response.
     */
    private function logSuccess(BraintreeResult $result): void
    {
        $this->gatewayLogger->logJsonResponse($result);
    }

    /**
     * Logs a Braintree error response.
     */
    private function logError(BraintreeError $result): void
    {
        $this->gatewayLogger->logJsonResponse($result);
    }

    private function logRequest(array $request): void
    {
        $requestXml = Xml::buildXmlFromArray($request);
        $this->gatewayLogger->logStringRequest($requestXml, self::MASK_REGEXES);
    }

    /**
     * Builds a Refund object from a Braintree transaction response.
     */
    private function buildRefund(BraintreeResult $result, Money $amount): RefundValueObject
    {
        /** @var BraintreeTransaction $transaction */
        $transaction = $result->transaction; /* @phpstan-ignore-line */

        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $transaction->id,
            status: RefundValueObject::SUCCEEDED,
        );
    }

    private function getMerchantAccountId(array $merchantAccounts, string $currency): string
    {
        foreach ($merchantAccounts as $account) {
            if ($account->currency == $currency) {
                return $account->id;
            }
        }

        throw new ChargeException('There is no merchant account matching the given currency.');
    }

    /**
     * Builds a Charge object from a Braintree transaction response.
     */
    private function buildCharge(BraintreeResult $result, PaymentSource $source, Money $amount, string $description): ChargeValueObject
    {
        /** @var BraintreeTransaction $transaction */
        $transaction = $result->transaction; /* @phpstan-ignore-line */
        $total = Money::fromDecimal($amount->currency, (float) $transaction->amount);

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $total,
            gateway: self::ID,
            gatewayId: $transaction->id,
            method: '',
            status: ChargeValueObject::SUCCEEDED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $transaction->processorResponseText,
        );
    }

    /**
     * Builds a Charge object from a failed Braintree transaction response.
     */
    private function buildFailedCharge(BraintreeError $result, PaymentSource $source, Money $amount, string $description): ChargeValueObject
    {
        /** @var BraintreeTransaction $transaction */
        $transaction = $result->transaction; /* @phpstan-ignore-line */
        $total = Money::fromDecimal($amount->currency, (float) $transaction->amount);

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $total,
            gateway: self::ID,
            gatewayId: $transaction->id,
            method: '',
            status: ChargeValueObject::FAILED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $transaction->processorResponseText,
        );
    }
}
