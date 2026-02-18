<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AuthorizeNet\AuthorizeNetHttpClient;
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
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1\ANetApiRequestType;
use net\authorize\api\contract\v1\ANetApiResponseType;
use net\authorize\api\contract\v1\BankAccountType;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\CreateTransactionResponse;
use net\authorize\api\contract\v1\CreditCardType;
use net\authorize\api\contract\v1\CustomerDataType;
use net\authorize\api\contract\v1\CustomerProfilePaymentType;
use net\authorize\api\contract\v1\DeleteCustomerPaymentProfileRequest;
use net\authorize\api\contract\v1\GetMerchantDetailsRequest;
use net\authorize\api\contract\v1\GetTransactionDetailsRequest;
use net\authorize\api\contract\v1\GetTransactionDetailsResponse;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\contract\v1\OrderType;
use net\authorize\api\contract\v1\PaymentMaskedType;
use net\authorize\api\contract\v1\PaymentProfileType;
use net\authorize\api\contract\v1\PaymentType;
use net\authorize\api\contract\v1\SettingType;
use net\authorize\api\contract\v1\SolutionType;
use net\authorize\api\contract\v1\TransactionDetailsType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\contract\v1\TransactionResponseType;
use net\authorize\api\controller\base\ApiOperationBase;
use net\authorize\api\controller\CreateTransactionController;
use net\authorize\api\controller\DeleteCustomerPaymentProfileController;
use net\authorize\api\controller\GetMerchantDetailsController;
use net\authorize\api\controller\GetTransactionDetailsController;
use net\authorize\util\Mapper;
use ReflectionClass;

class AuthorizeNetGateway extends AbstractLegacyGateway implements RefundInterface, VoidInterface, TestCredentialsInterface, TransactionStatusInterface
{
    const ID = 'authorizenet';

    private const TRANSACTION_SALE = 'authCaptureTransaction';
    private const TRANSACTION_VOID = 'voidTransaction';
    private const TRANSACTION_REFUND = 'refundTransaction';

    private const SOLUTION_ID = 'AAA171472';
    private const SOLUTION_ID_SANDBOX = 'AAA100302';

    private const TRANSACTION_APPROVED = '1';

    private const RESPONSE_OK = 'Ok';

    private const ERROR_CODE_NOT_FOUND = '16';
    private const ERROR_CODE_ACCESS_DENIED = 'E00011';

    private const ACH_TYPE_SAVINGS = 'savings';
    private const ACH_TYPE_CHECKING = 'checking';

    private const MASK_PARAMETERS = [
        'transactionKey',
        'cardNumber',
        'cardCode',
        'accountNumber',
    ];

    private TransactionResponseType $voidTransactionResponse;

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->login_id)) {
            throw new InvalidGatewayConfigurationException('Missing Authorize.Net login ID!');
        }

        if (!isset($gatewayConfiguration->credentials->transaction_key)) {
            throw new InvalidGatewayConfigurationException('Missing Authorize.Net transaction key!');
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

        $transactionRequestType = $this->buildSale($source, $amount, $documents, $description, $parameters);

        // set the payment information
        [$customerProfileId, $paymentProfileId] = $this->parseSourceId($source);
        $profileToCharge = new CustomerProfilePaymentType();
        $profileToCharge->setCustomerProfileId($customerProfileId);
        $paymentProfile = new PaymentProfileType();
        $paymentProfile->setPaymentProfileId($paymentProfileId);
        if (isset($parameters['cvc'])) {
            $paymentProfile->setCardCode($parameters['cvc']);
        }
        $profileToCharge->setPaymentProfile($paymentProfile);
        $transactionRequestType->setProfile($profileToCharge);

        // send the request to auth.net
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $response = $this->performTransaction($gatewayConfiguration, $transactionRequestType);

        if ($error = $this->checkForAuthError($response)) {
            throw new ChargeException($error);
        }

        // parse the response
        $transactionResponse = $response->getTransactionResponse();
        if (!$transactionResponse || !$transactionResponse->getTransId()) { /* @phpstan-ignore-line */
            throw new ChargeException($this->buildResponseErrorMessage($response));
        }

        if (self::TRANSACTION_APPROVED != $transactionResponse->getResponseCode()) {
            // build a failed charge when available
            $charge = $this->buildFailedCharge($transactionResponse, $response, $source, $amount, $account, $description);

            throw new ChargeException($this->buildTransactionErrorMessage($transactionResponse, $response), $charge);
        }

        return $this->buildCharge($transactionResponse, $source, $amount, $account, $description);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        [$customerProfileId, $paymentProfileId] = $this->parseSourceId($source);

        // build credentials
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $merchantAuthentication = $this->buildMerchantAuthentication($gatewayConfiguration);

        // determine environment
        if (isset($gatewayConfiguration->credentials->test_mode) && $gatewayConfiguration->credentials->test_mode) {
            $environment = ANetEnvironment::SANDBOX;
        } else {
            $environment = ANetEnvironment::PRODUCTION;
        }

        // construct the request
        $request = new DeleteCustomerPaymentProfileRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setCustomerProfileId($customerProfileId);
        $request->setCustomerPaymentProfileId($paymentProfileId);
        $controller = new DeleteCustomerPaymentProfileController($request);

        // send the request to auth.net
        $response = $this->performRequest($controller, $request, $environment);

        // check for an authentication error
        if ($error = $this->checkForAuthError($response)) {
            throw new PaymentSourceException($error);
        }

        // parse the response
        if (self::RESPONSE_OK != $response->getMessages()->getResultCode()) {
            throw new PaymentSourceException($this->buildResponseErrorMessage($response));
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

            return $this->buildRefund($this->voidTransactionResponse, $amount, $chargeId);
        } catch (VoidException) {
            // do nothing
        }

        return $this->credit($merchantAccount, $amount, $chargeId);
    }

    public function void(MerchantAccount $merchantAccount, string $chargeId): void
    {
        // create a transaction request
        $transactionRequestType = new TransactionRequestType();
        $transactionRequestType->setTransactionType(self::TRANSACTION_VOID);
        $transactionRequestType->setRefTransId($chargeId);

        // send the request to auth.net
        $response = $this->performTransaction($merchantAccount->toGatewayConfiguration(), $transactionRequestType);

        if ($error = $this->checkForAuthError($response)) {
            throw new VoidException($error);
        }

        $transactionResponse = $response->getTransactionResponse();
        if (!$transactionResponse) { /* @phpstan-ignore-line */
            throw new VoidException($this->buildResponseErrorMessage($response));
        }

        if (self::TRANSACTION_APPROVED != $transactionResponse->getResponseCode() || !$transactionResponse->getTransId()) {
            // if the original transaction cannot be found when issuing a void
            // then that means it has already been settled
            $errors = $transactionResponse->getErrors();
            if ($errors) {
                foreach ($errors as $part) {
                    if (self::ERROR_CODE_NOT_FOUND == $part->getErrorCode()) {
                        throw new VoidAlreadySettledException('Already settled');
                    }
                }
            }

            throw new VoidException($this->buildTransactionErrorMessage($transactionResponse, $response));
        }

        $this->voidTransactionResponse = $transactionResponse;
    }

    /**
     * Issues a credit for a previous transaction.
     *
     * @throws RefundException
     */
    private function credit(MerchantAccount $account, Money $amount, string $chargeId): RefundValueObject
    {
        // create a transaction request
        $transactionRequestType = new TransactionRequestType();
        $transactionRequestType->setTransactionType(self::TRANSACTION_REFUND);
        $transactionRequestType->setAmount($amount->toDecimal());
        $transactionRequestType->setRefTransId($chargeId);

        // Authorize.Net requires that CC refunds include the masked card number
        // and the card expiration date. ACH refunds must include the account
        // #, routing #, and account holder name. We do not have that information
        // available here so we need to look up the original transaction.
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $originalPayment = $this->getOriginalPayment($gatewayConfiguration, $chargeId);
        $payment = $this->convertMaskedPayment($originalPayment);
        $transactionRequestType->setPayment($payment);

        // send the request to auth.net
        $response = $this->performTransaction($gatewayConfiguration, $transactionRequestType);

        if ($error = $this->checkForAuthError($response)) {
            throw new RefundException($error);
        }

        // parse the response
        $transactionResponse = $response->getTransactionResponse();
        if (!$transactionResponse || !$transactionResponse->getTransId()) { /* @phpstan-ignore-line */
            throw new RefundException($this->buildResponseErrorMessage($response));
        }

        if (self::TRANSACTION_APPROVED != $transactionResponse->getResponseCode()) {
            throw new RefundException($this->buildTransactionErrorMessage($transactionResponse, $response));
        }

        return $this->buildRefund($transactionResponse, $amount, $chargeId);
    }

    //
    // Transaction Status
    //

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        $chargeId = $charge->gateway_id;
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $merchantAuthentication = $this->buildMerchantAuthentication($gatewayConfiguration);

        if (isset($gatewayConfiguration->credentials->test_mode) && $gatewayConfiguration->credentials->test_mode) {
            $environment = ANetEnvironment::SANDBOX;
        } else {
            $environment = ANetEnvironment::PRODUCTION;
        }

        $request = new GetTransactionDetailsRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setTransId($chargeId);

        $controller = new GetTransactionDetailsController($request);

        $response = $this->performRequest($controller, $request, $environment);

        if ('Ok' != $response->getMessages()->getResultCode()) {
            $errorMessages = $response->getMessages()->getMessage();

            throw new TransactionStatusException($errorMessages[0]->getText());
        }

        $transaction = $response->getTransaction(); /* @phpstan-ignore-line */

        return $this->buildTransactionStatus($transaction);
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        $merchantAuthentication = $this->buildMerchantAuthentication($gatewayConfiguration);

        if (isset($gatewayConfiguration->credentials->test_mode) && $gatewayConfiguration->credentials->test_mode) {
            $environment = ANetEnvironment::SANDBOX;
        } else {
            $environment = ANetEnvironment::PRODUCTION;
        }

        $request = new GetMerchantDetailsRequest();
        $request->setMerchantAuthentication($merchantAuthentication);

        $controller = new GetMerchantDetailsController($request);

        $response = $this->performRequest($controller, $request, $environment);

        if ('Ok' != $response->getMessages()->getResultCode()) {
            $errorMessages = $response->getMessages()->getMessage();

            throw new TestGatewayCredentialsException($errorMessages[0]->getText());
        }
    }

    //
    // Helpers
    //

    /**
     * Builds an authentication object.
     */
    private function buildMerchantAuthentication(PaymentGatewayConfiguration $gatewayConfiguration): MerchantAuthenticationType
    {
        $merchantAuthentication = new MerchantAuthenticationType();
        $merchantAuthentication->setName($gatewayConfiguration->credentials->login_id);
        $merchantAuthentication->setTransactionKey($gatewayConfiguration->credentials->transaction_key);

        return $merchantAuthentication;
    }

    /**
     * Performs a request against the Authorize.Net API with
     * request/response logging.
     */
    private function performRequest(ApiOperationBase $controller, ANetApiRequestType $request, string $environment): ANetApiResponseType
    {
        $controller->httpClient = new AuthorizeNetHttpClient($this->gatewayLogger);
        $response = $controller->executeWithApiResponse($environment);
        $this->logRequest($request);

        return $response;
    }

    /**
     * Logs an Authorize.Net API request in a PCI compliant
     * manner by scrubbing sensitive data first.
     */
    private function logRequest(ANetApiRequestType $request): void
    {
        $mapper = Mapper::Instance();
        $requestRoot = $mapper->getXmlName((new ReflectionClass($request))->getName());
        // Convert request from an object into an array
        $requestSubArray = json_decode((string) json_encode($request), true);
        $requestArray = [$requestRoot => $requestSubArray];

        $this->gatewayLogger->logJsonRequest($requestArray, self::MASK_PARAMETERS);
    }

    /**
     * Checks for an authentication error.
     */
    private function checkForAuthError(ANetApiResponseType $response): ?string
    {
        $error = $response->getMessages()->getMessage()[0];
        if ('E00007' === $error->getCode()) {
            return $error->getText();
        }

        return null;
    }

    /**
     * Builds an error message from an Authorize.Net response.
     */
    private function buildResponseErrorMessage(ANetApiResponseType $response): string
    {
        // then check for messages
        $message = null;
        $messages = $response->getMessages()->getMessage();
        if ($messages) {
            $message = [];
            foreach ($messages as $part) {
                $message[] = $part->getText();
            }

            $message = implode(' ', $message);
        }

        return $message ?: 'An unknown error has occurred';
    }

    /**
     * Parses a payment source ID generated by this integration.
     *
     * @return array [customer ID, payment profile ID]
     */
    private function parseSourceId(PaymentSource $source): array
    {
        if (!$source->gateway_customer) {
            return explode(':', (string) $source->gateway_id);
        }

        return [$source->gateway_customer, $source->gateway_id];
    }

    /**
     * @throws TransactionStatusException
     */
    private function buildTransactionStatus(TransactionDetailsType $transaction): array
    {
        if (in_array($transaction->getTransactionStatus(), ['authorizedPendingCapture', 'capturedPendingSettlement', 'underReview', 'FDSPendingReview', 'FDSAuthorizedPendingReview', 'refundPendingSettlement'])) {
            $status = ChargeValueObject::PENDING;
        } elseif (in_array($transaction->getTransactionStatus(), ['declined', 'expired', 'communicationError', 'generalError'])) {
            $status = ChargeValueObject::FAILED;
        } elseif (in_array($transaction->getTransactionStatus(), ['settledSuccessfully', 'refundSettledSuccessfully'])) {
            $status = ChargeValueObject::SUCCEEDED;
        } else {
            throw new TransactionStatusException('Invalid status');
        }

        return [$status, $transaction->getTransactionStatus()];
    }

    /**
     * Performs a transaction against an Auth.net account.
     */
    private function performTransaction(PaymentGatewayConfiguration $gatewayConfiguration, TransactionRequestType $transactionRequestType): CreateTransactionResponse
    {
        // build credentials
        $merchantAuthentication = $this->buildMerchantAuthentication($gatewayConfiguration);

        // determine environment
        if (isset($gatewayConfiguration->credentials->test_mode) && $gatewayConfiguration->credentials->test_mode) {
            $environment = ANetEnvironment::SANDBOX;
            $solutionId = self::SOLUTION_ID_SANDBOX;
        } else {
            $environment = ANetEnvironment::PRODUCTION;
            $solutionId = self::SOLUTION_ID;
        }

        // add solution ID
        $solution = new SolutionType();
        $solution->setId($solutionId);
        $transactionRequestType->setSolution($solution);

        // construct the request
        $request = new CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $refId = 'ref'.time();
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequestType);
        $controller = new CreateTransactionController($request);

        // finally can send it off
        return $this->performRequest($controller, $request, $environment); /* @phpstan-ignore-line */
    }

    /**
     * Builds an error message from an Authorize.Net transaction response.
     */
    private function buildTransactionErrorMessage(TransactionResponseType $transactionResponse, ANetApiResponseType $response): string
    {
        // first check the transaction for errors
        $errors = $transactionResponse->getErrors();
        if ($errors) {
            $message = [];
            foreach ($errors as $part) {
                $message[] = $part->getErrorText();
            }

            $message = implode(' ', $message);
            if ($message) {
                return $message;
            }
        }

        // then check for messages
        $messages = $transactionResponse->getMessages();
        if ($messages) {
            $message = [];
            foreach ($messages as $part) {
                $message[] = $part->getDescription();
            }

            $message = implode(' ', $message);
            if ($message) {
                return $message;
            }
        }

        // fallback to check the response for error messages last
        return $this->buildResponseErrorMessage($response);
    }

    /**
     * Builds a Refund object from an Auth.net transaction response.
     */
    private function buildRefund(TransactionResponseType $transactionResponse, Money $amount, string $chargeId): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $transactionResponse->getTransId(),
            status: RefundValueObject::SUCCEEDED,
            message: $transactionResponse->getMessages()[0]->getDescription(),
        );
    }

    /**
     * Gets the original payment from a previous transaction.
     *
     * NOTE: This requires that the user has enabled the
     * Transaction Details API in their Authorize.Net account.
     *
     * @throws RefundException
     */
    private function getOriginalPayment(PaymentGatewayConfiguration $gatewayConfiguration, string $chargeId): PaymentMaskedType
    {
        $details = $this->getTransactionDetails($gatewayConfiguration, $chargeId);
        $messages = $details->getMessages();
        if (self::RESPONSE_OK != $messages->getResultCode()) {
            $code = $messages->getMessage()[0]->getCode();
            if (self::ERROR_CODE_ACCESS_DENIED == $code) {
                throw new RefundException('Access was denied when attempting to look up the transaction on Authorize.Net. Please enable the Transaction Details API in your Authorize.Net security settings and then retry this request.');
            }

            throw new RefundException($this->buildResponseErrorMessage($details));
        }

        return $details->getTransaction()->getPayment();
    }

    /**
     * Converts a masked payment type to a payment type.
     */
    private function convertMaskedPayment(PaymentMaskedType $masked): PaymentType
    {
        $payment = new PaymentType();

        // Credit cards
        if ($originalCard = $masked->getCreditCard()) { /** @phpstan-ignore-line */
            $creditCard = new CreditCardType();
            $creditCard->setCardNumber($originalCard->getCardNumber());
            $creditCard->setExpirationDate($originalCard->getExpirationDate());
            $payment->setCreditCard($creditCard);
        }

        // ACH
        if ($originalBankAccount = $masked->getBankAccount()) { /** @phpstan-ignore-line */
            $bankAccount = new BankAccountType();
            $bankAccount->setAccountNumber($originalBankAccount->getAccountNumber());
            $bankAccount->setRoutingNumber($originalBankAccount->getRoutingNumber());
            $bankAccount->setNameOnAccount($originalBankAccount->getNameOnAccount());
            $payment->setBankAccount($bankAccount);
        }

        return $payment;
    }

    /**
     * Looks up a transaction by ID.
     */
    private function getTransactionDetails(PaymentGatewayConfiguration $gatewayConfiguration, string $transactionId): GetTransactionDetailsResponse
    {
        // build credentials
        $merchantAuthentication = $this->buildMerchantAuthentication($gatewayConfiguration);

        // determine environment
        if (isset($gatewayConfiguration->credentials->test_mode) && $gatewayConfiguration->credentials->test_mode) {
            $environment = ANetEnvironment::SANDBOX;
        } else {
            $environment = ANetEnvironment::PRODUCTION;
        }

        // construct the request
        $request = new GetTransactionDetailsRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setTransId($transactionId);
        $refId = 'ref'.time();
        $request->setRefId($refId);
        $controller = new GetTransactionDetailsController($request);

        // finally can send it off
        return $this->performRequest($controller, $request, $environment); /* @phpstan-ignore-line */
    }

    /**
     * Builds a sale transaction request.
     *
     * @property ReceivableDocument[] $documents
     */
    private function buildSale(PaymentSource $source, Money $amount, array $documents, string $description, array $parameters): TransactionRequestType
    {
        // pass in extra transaction metadata
        $order = new OrderType();
        $customerData = new CustomerDataType();
        if (count($documents) > 0) {
            $order->setInvoiceNumber((string) $documents[0]->id);
        }
        $customerData->setId((string) $source->customer_id);
        $order->setDescription($description);
        if ($email = GatewayHelper::getEmail($source->customer, $parameters)) {
            $customerData->setEmail($email);
        }

        // create a transaction request
        $transactionRequestType = new TransactionRequestType();
        $duplicateWindowSetting = new SettingType();
        $duplicateWindowSetting->setSettingName('duplicateWindow');
        $duplicateWindowSetting->setSettingValue('0');
        $duplicateWindowSetting->setSettingName('allowPartialAuth');
        $duplicateWindowSetting->setSettingValue('0');
        $transactionRequestType->addToTransactionSettings($duplicateWindowSetting);
        $transactionRequestType->setTransactionType(self::TRANSACTION_SALE);
        $transactionRequestType->setAmount($amount->toDecimal());
        $transactionRequestType->setCurrencyCode($amount->currency);
        $transactionRequestType->setOrder($order);
        $transactionRequestType->setCustomer($customerData);

        return $transactionRequestType;
    }

    /**
     * Builds a charge object from an Auth.net transaction response.
     */
    private function buildCharge(TransactionResponseType $transactionResponse, PaymentSource $source, Money $amount, MerchantAccount $merchantAccount, string $description): ChargeValueObject
    {
        // NOTE We have set allowPartialAuth to false, which should
        // prevent partial authorizations on prepaid cards. This
        // means it should be safe to assume the charge was for the
        // requested amount.

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $transactionResponse->getTransId(),
            method: '',
            status: ChargeValueObject::SUCCEEDED,
            merchantAccount: $merchantAccount,
            source: $source,
            description: $description,
            failureReason: $transactionResponse->getMessages()[0]->getDescription(),
        );
    }

    /**
     * Builds a charge object from a failed Auth.net transaction response.
     */
    private function buildFailedCharge(TransactionResponseType $transactionResponse, ANetApiResponseType $response, PaymentSource $source, Money $amount, MerchantAccount $merchantAccount, string $description): ChargeValueObject
    {
        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $transactionResponse->getTransId(),
            method: '',
            status: ChargeValueObject::FAILED,
            merchantAccount: $merchantAccount,
            source: $source,
            description: $description,
            failureReason: $this->buildTransactionErrorMessage($transactionResponse, $response),
        );
    }

    /**
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function chargeBankAccount(BankAccount $bankAccount, MerchantAccount $account, Money $amount, array $parameters, array $documents, string $description): ChargeValueObject
    {
        $transactionRequestType = $this->buildSale($bankAccount, $amount, $documents, $description, $parameters);

        // set the payment information
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $payment = $this->buildAchPayment($gatewayConfiguration, $bankAccount);
        $transactionRequestType->setPayment($payment);

        // send the request to auth.net
        $response = $this->performTransaction($gatewayConfiguration, $transactionRequestType);

        if ($error = $this->checkForAuthError($response)) {
            throw new ChargeException($error);
        }

        // parse the response
        $transactionResponse = $response->getTransactionResponse();
        if (!$transactionResponse) { /* @phpstan-ignore-line */
            throw new ChargeException($this->buildResponseErrorMessage($response));
        }

        if (self::TRANSACTION_APPROVED != $transactionResponse->getResponseCode()) {
            // build a failed charge when available
            $charge = $this->buildFailedCharge($transactionResponse, $response, $bankAccount, $amount, $account, $description);

            throw new ChargeException($this->buildTransactionErrorMessage($transactionResponse, $response), $charge);
        }

        return $this->buildCharge($transactionResponse, $bankAccount, $amount, $account, $description);
    }

    /**
     * Creates a bank account payment type for Authorize.Net.
     */
    private function buildAchPayment(PaymentGatewayConfiguration $gatewayConfiguration, BankAccount $bankAccount): PaymentType
    {
        $bankAccountType = new BankAccountType();
        $bankAccountType->setEcheckType(GatewayHelper::secCodeWeb($gatewayConfiguration));
        $bankAccountType->setRoutingNumber((string) $bankAccount->routing_number);
        $bankAccountType->setAccountNumber((string) $bankAccount->account_number);
        $bankAccountType->setNameOnAccount(substr((string) $bankAccount->account_holder_name, 0, 22));
        $bankAccountType->setBankName($bankAccount->bank_name);

        if (BankAccountValueObject::TYPE_SAVINGS == $bankAccount->type) {
            $bankAccountType->setAccountType(self::ACH_TYPE_SAVINGS);
        } else {
            $bankAccountType->setAccountType(self::ACH_TYPE_CHECKING);
        }

        $payment = new PaymentType();
        $payment->setBankAccount($bankAccountType);

        return $payment;
    }
}
