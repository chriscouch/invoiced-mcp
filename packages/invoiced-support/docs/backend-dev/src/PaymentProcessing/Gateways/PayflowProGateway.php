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
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\Level3Data;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class PayflowProGateway extends AbstractLegacyGateway implements RefundInterface, VoidInterface, TestCredentialsInterface, TransactionStatusInterface
{
    const ID = 'payflowpro';

    private const API_URL = 'https://payflowpro.paypal.com';
    private const TEST_MODE_API_URL = 'https://pilot-payflowpro.paypal.com';

    private const TRANSACTION_AUTHORIZATION = 'A';
    private const TRANSACTION_SALE = 'S';
    private const TRANSACTION_CREDIT = 'C';
    private const TRANSACTION_VOID = 'V';
    private const TRANSACTION_INQUIRY = 'I'; // for transaction status & details

    private const TENDER_CREDIT_CARD = 'C';
    private const TENDER_BANK_ACCOUNT = 'A';

    private const ACCOUNT_TYPE_CHECKING = 'C';
    private const ACCOUNT_TYPE_SAVINGS = 'S';

    private const IS_FINAL_CAPTURE = 'Y';

    private const ECHO_CUSTOMER_DATA = 'custdata';

    // All other response codes are declines and messages are included with them.
    // This section only contains those we sometimes need to explicitly check for.
    private const RESPONSE_APPROVED = '0';
    private const RESPONSE_AUTH_FAILURE = '1';
    private const RESPONSE_FAILED_RULE_CHECK = '117';
    private const RESPONSE_VOID_ERROR = '108';

    // Transaction states above 1000 indicate they were in like state less 1000 but later voided.
    private const TRANSACTION_STATES_SUCCEEDED = ['0', '8', '9', '1000', '1008', '1009'];

    private const TRANSACTION_STATES_PENDING = ['3', '6', '7', '206'];

    private const TRANSACTION_STATE_MESSAGES = [
        '0' => 'Account verification transaction (no settlement involved)',
        '1' => 'Unknown error',
        '3' => 'Authorization approved',
        '4' => 'Partial capture',
        '6' => 'Settlement pending',
        '7' => 'Settlement in progress',
        '8' => 'Transaction settled successfully',
        '9' => 'Authorization captured',
        '10' => 'Capture failed',
        '11' => 'Failed to settle',
        '12' => 'Failed to settle due to incorrect account information',
        '14' => 'The batch containing this transaction failed settlement',
        '15' => 'Settlement incomplete due to a chargeback',
        '16' => 'Merchant ACH settlement failed',
        '106' => 'Unknown transaction status (not settled)',
        '206' => 'Transaction on hold pending customer intervention',
    ];

    private const MASKED_REQUEST_PARAMETERS = [
        'PWD',
        'ACCT',
        'CVV2',
    ];

    private array $voidResult;

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->vendor)) {
            throw new InvalidGatewayConfigurationException('Missing Payflow Pro vendor ID!');
        }

        if (!isset($gatewayConfiguration->credentials->user)) {
            throw new InvalidGatewayConfigurationException('Missing Payflow Pro username!');
        }

        if (!isset($gatewayConfiguration->credentials->password)) {
            throw new InvalidGatewayConfigurationException('Missing Payflow Pro password!');
        }
    }

    //
    // One-Time Charges
    //

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $paymentMethod = $parameters['payment_method'] ?? '';
        if ('ach' == $paymentMethod) {
            return $this->chargeBankAccount($customer, $account, $amount, $parameters, $documents, $description);
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
        $account = $source->getMerchantAccount();
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $client = $this->getClient($gatewayConfiguration);

        $request = $this->buildRequestSkeleton($gatewayConfiguration);
        // configure transaction
        if ($source instanceof Card) {
            $request['ORIGID'] = $source->gateway_id;
            $request['TENDER'] = self::TENDER_CREDIT_CARD;
        } elseif ($source instanceof BankAccount) {
            $request = $this->withBankAccount($gatewayConfiguration, $request, $source);
            // Required for CCD. Optional for WEB.
            $request['DESC'] = $description;
        } else {
            throw new ChargeException('Unrecognized payment source type: '.$source->object);
        }

        $successState = ChargeValueObject::SUCCEEDED;

        if (isset($parameters['capture']) && !$parameters['capture']) {
            $request['TRXTYPE'] = self::TRANSACTION_AUTHORIZATION;
            $successState = ChargeValueObject::AUTHORIZED;
        } else {
            $request['TRXTYPE'] = self::TRANSACTION_SALE;
        }

        $request['AMT'] = $amount->toDecimal();
        $request['CURRENCY'] = strtoupper($amount->currency);

        if ($source instanceof Card) {
            $level3 = GatewayHelper::makeLevel3($documents, $source->customer, $amount);
            $request = $this->withLevel3Data($request, $level3);
        }

        // perform request
        $response = $this->performRequest($client, $request, false, ChargeException::class);

        if (self::RESPONSE_APPROVED == $response['RESULT']) {
            // make sure currency matches actual response; PayPal does not error
            // or inform you when currency requested is not used, i.e. if the user
            // requests transaction in AUD and this is disabled, it will silently
            // use USD. Charge should always reflect what was actually returned.
            if (isset($response['CURRENCY'])) {
                $amount = new Money($response['CURRENCY'], $amount->amount);
            }

            return $this->buildCharge($response, $amount, $source, $successState, $description);
        }

        $failedCharge = $this->buildFailedCharge($response, $amount, $source, $description);

        throw new ChargeException($response['RESPMSG'], $failedCharge);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        // PayPal Payflow Pro does not support deleting payment information. Do nothing to let it
        // be deleted from our database.
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
            $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
            $client = $this->getClient($gatewayConfiguration);

            return $this->buildRefund($client, $gatewayConfiguration, $this->voidResult, $amount);
        } catch (VoidException) {
            // do nothing
        }

        return $this->credit($merchantAccount, $chargeId, $amount);
    }

    public function void(MerchantAccount $merchantAccount, string $chargeId): void
    {
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $request = $this->buildRequestSkeleton($gatewayConfiguration);

        $request['TRXTYPE'] = self::TRANSACTION_VOID;
        $request['ORIGID'] = $chargeId;

        // indicates we do not intend to interact further with this transaction
        $request['CAPTURECOMPLETE'] = self::IS_FINAL_CAPTURE;

        $client = $this->getClient($gatewayConfiguration);
        $this->voidResult = $this->performRequest($client, $request, false, RefundException::class);

        if (self::RESPONSE_APPROVED == $this->voidResult['RESULT']) {
            return;
        }

        if (self::RESPONSE_VOID_ERROR == $this->voidResult['RESULT']) {
            throw new VoidAlreadySettledException('Already settled');
        }

        throw new VoidException($this->voidResult['RESPMSG']);
    }

    //
    // Transaction Status
    //

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        $chargeId = $charge->gateway_id;
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $client = $this->getClient($gatewayConfiguration);

        // get transaction details, then build object based on state of response
        $request = $this->buildRequestSkeleton($gatewayConfiguration);

        $request['TRXTYPE'] = self::TRANSACTION_INQUIRY;
        $request['ORIGID'] = $chargeId;

        $transaction = $this->performRequest($client, $request, true, TransactionStatusException::class);

        $state = $transaction['TRANSSTATE'];

        $status = ChargeValueObject::FAILED;
        if (in_array($state, self::TRANSACTION_STATES_SUCCEEDED)) {
            $status = ChargeValueObject::SUCCEEDED;
        } elseif (in_array($state, self::TRANSACTION_STATES_PENDING)) {
            $status = ChargeValueObject::PENDING;
        }

        $message = self::TRANSACTION_STATE_MESSAGES[$state] ?? 'Unknown transaction state';

        return [$status, $message];
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        $client = $this->getClient($gatewayConfiguration);

        $request = $this->buildRequestSkeleton($gatewayConfiguration);
        $request['TRXTYPE'] = self::TRANSACTION_INQUIRY;

        $response = $this->performRequest($client, $request, false, TestGatewayCredentialsException::class);

        $valid = self::RESPONSE_AUTH_FAILURE != $response['RESULT'];

        if (!$valid) {
            throw new TestGatewayCredentialsException('Invalid gateway credentials');
        }
    }

    //
    // Helpers
    //

    /**
     * Builds usable client based on whether passed MerchantAccount is in test mode.
     */
    private function getClient(PaymentGatewayConfiguration $gatewayConfiguration): Client
    {
        $url = self::API_URL;

        if ($gatewayConfiguration->credentials->test_mode) {
            $url = self::TEST_MODE_API_URL;
        }

        $headers = [
            'Content-Type' => 'text/namevalue; charset=UTF-8',
            'Accept' => 'text/namevalue',
        ];

        return HttpClientFactory::make($this->gatewayLogger, [
            'base_uri' => $url,
            'headers' => $headers,
        ]);
    }

    /**
     * Builds components of requests that must be in all requests.
     */
    private function buildRequestSkeleton(PaymentGatewayConfiguration $gatewayConfiguration): array
    {
        return [
            'PARTNER' => $gatewayConfiguration->credentials->partner,
            'VENDOR' => $gatewayConfiguration->credentials->vendor,
            'USER' => $gatewayConfiguration->credentials->user,
            'PWD' => $gatewayConfiguration->credentials->password,
            'VERBOSITY' => 'HIGH',
            'ECHODATA' => self::ECHO_CUSTOMER_DATA,
        ];
    }

    /**
     * Performs request against gateway. Error handling is included in here.
     *
     * Throws exception on any failure by default; set this param to false
     * to handle errors manually outside this function call. Always throws
     * exception if gateway can't be reached.
     */
    private function performRequest(Client $client, array $request, bool $throwExceptionOnFail, string $exceptionType): array
    {
        // first we take our array and flatten it into text/namevalues
        $paramsJoined = [];
        $loggableParamsJoined = [];

        foreach ($request as $k => $v) {
            // remove quotation marks; only chars that can never be used
            // other special chars are fine due to adding lengths to params
            $v = (string) str_replace('"', '', $v);
            $v = (string) str_replace("'", '', $v);

            // limit length of any given value to 30 per PayPal
            if (strlen($v) > 30) {
                $v = substr($v, 0, 30);
            }

            // make loggable version of k=v pair in same loop
            $loggableV = $v;
            if (in_array(strtoupper($k), self::MASKED_REQUEST_PARAMETERS)) {
                $loggableV = str_repeat('x', strlen($v));
            }

            // join params and add length specification for value;
            // per PayPal docs, this avoids special character issues
            $paramsJoined[] = "$k".'['.strlen($v)."]=$v";
            $loggableParamsJoined[] = "$k".'['.strlen($v)."]=$loggableV";
        }
        $paramString = implode('&', $paramsJoined);
        $loggableParamString = implode('&', $loggableParamsJoined);

        $this->gatewayLogger->logStringRequest($loggableParamString, []);

        try {
            $response = $client->request('POST', '', ['body' => $paramString]);
        } catch (GuzzleException) {
            // As long as we get results back, HTTP 200 OK is returned,
            // so this is also a generic message. Error messages are downstream.
            throw new $exceptionType('An unknown error occurred when communicating with the Payflow Pro gateway.'); /* @phpstan-ignore-line */
        }

        $result = $this->parseResponse($response);

        if ($throwExceptionOnFail) {
            $this->handleErrorCase($result, $exceptionType);
        }

        return $result;
    }

    /**
     * Parses response from the gateway.
     */
    private function parseResponse(ResponseInterface $response): array
    {
        // manually split out values from name-value to k => v pairs
        $exploded = explode('&', (string) $response->getBody());

        $result = [];
        foreach ($exploded as $pair) {
            $v = explode('=', $pair);
            $result[$v[0]] = $v[1];
        }

        return $result;
    }

    /**
     * Checks for errors and throws exception if one is encountered.
     * Exception type is configurable to allow cleaner function reuse.
     *
     * We don't need to hardcode errors because messages accompany them
     * in API response contents.
     */
    private function handleErrorCase(array $response, string $exceptionType): void
    {
        if (self::RESPONSE_APPROVED != $response['RESULT']) {
            if (isset($response['RESPMSG'])) {
                throw new $exceptionType($response['RESPMSG']); /* @phpstan-ignore-line */
            }

            throw new $exceptionType('An unknown error occurred when communicating with the Payflow Pro gateway.'); /* @phpstan-ignore-line */
        }
    }

    /**
     * Credits an existing transaction on the gateway by ID.
     *
     * @throws RefundException
     */
    private function credit(MerchantAccount $account, string $chargeId, Money $amount): RefundValueObject
    {
        $gatewayConfiguration = $account->toGatewayConfiguration();

        $request = $this->buildRequestSkeleton($gatewayConfiguration);
        $request['TRXTYPE'] = self::TRANSACTION_CREDIT;
        $request['ORIGID'] = $chargeId;
        $request['AMT'] = $amount->toDecimal();
        $request['CURRENCY'] = strtoupper($amount->currency);

        $client = $this->getClient($gatewayConfiguration);
        $result = $this->performRequest($client, $request, false, RefundException::class);

        if (self::RESPONSE_APPROVED == $result['RESULT']) {
            return $this->buildRefund($client, $gatewayConfiguration, $result, $amount);
        }

        if (self::RESPONSE_FAILED_RULE_CHECK == $result['RESULT']) {
            throw new RefundException('Refund amount would result in negative net transaction amount');
        }

        throw new RefundException($result['RESPMSG']);
    }

    /**
     * Builds refund object from passed gateway response.
     *
     * Param $backupAmount is used when it is not possible to
     * retrieve amount and currency information from transaction
     * inquiry to PayPal. This occurs in the case of ACH; the
     * returns there are extremely limited. Otherwise, this
     * amount is not used.
     *
     * @throws RefundException
     */
    private function buildRefund(Client $client, PaymentGatewayConfiguration $gatewayConfiguration, array $result, Money $backupAmount = null): RefundValueObject
    {
        // we have to look up the refund transaction because most
        // responses do not return the amount, which can differ from
        // requested amount in rare cases, and we make sure we return
        // the correct PNREF (for the original transaction). We grab
        // currency from here as well to be safe.

        $request = $this->buildRequestSkeleton($gatewayConfiguration);
        $request['TRXTYPE'] = self::TRANSACTION_INQUIRY;
        $request['ORIGID'] = $result['PNREF'];

        $completeTransaction = $this->performRequest($client, $request, true, RefundException::class);

        // Unfortunately PayPal gives us almost no ability to inquire about
        // ACH transactions after they are approved; in the case of refunds,
        // this means we have to populate the refund based on the requested
        // refund, not the actual. Because this function is only called when
        // the result code is 0, this is a low risk, and better than
        // returning no object, but it is not ideal.

        // We can use the existence of ORIGPNREF in the response to infer
        // whether the response relates to a card or ACH transaction. In the
        // former case, it is always returned; in the latter, never.

        if (isset($completeTransaction['ORIGPNREF'])) {
            $id = $completeTransaction['ORIGPNREF'];
            $currency = $completeTransaction['CURRENCY'];
            $amount = new Money($currency, intval(floatval($completeTransaction['AMT']) * 100));
        } elseif ($backupAmount) {
            $id = $result['PNREF'];
            $amount = $backupAmount;
        } else {
            throw new RefundException('Can\'t retrieve amount from anywhere!');
        }

        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: $id,
            status: RefundValueObject::SUCCEEDED,
        );
    }

    /**
     * Constructs a charge object from a gateway response.
     */
    private function buildCharge(array $response, Money $amount, PaymentSource $source, string $status, string $description): ChargeValueObject
    {
        // WARNING: PayPal does not return in any transaction response whether
        // the transaction was an authorization or a capture. This is why it
        // is possible to pass in the status; logic outside this function is
        // ultimately responsible for passing in the correct transaction
        // state for credit card transactions.

        if ($source instanceof BankAccount) {
            $status = ChargeValueObject::PENDING;
        }

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $response['PNREF'],
            method: '',
            status: $status,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $response['RESPMSG'],
        );
    }

    /**
     * Constructs a failed charge object from a gateway response.
     */
    private function buildFailedCharge(array $response, Money $amount, PaymentSource $source, string $description): ChargeValueObject
    {
        return new ChargeValueObject(
            customer: $source->customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $response['PNREF'] ?? '',
            method: '',
            status: ChargeValueObject::FAILED,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: $response['RESPMSG'],
        );
    }

    /**
     * Adds level 3 data to a card transaction request.
     *
     * This function assumes data is present, as it is not
     * otherwise called.
     */
    private function withLevel3Data(array $request, Level3Data $level3): array
    {
        $request = $this->addIfExists($request, 'PONUM', $level3->poNumber);
        $request = $this->addIfExists($request, 'MERCHANTZIP', $level3->merchantPostalCode);
        $request = $this->addIfExists($request, 'COMMCODE', $level3->summaryCommodityCode);
        $request = $this->addIfExists($request, 'TAXAMT', $level3->salesTax->toDecimal());
        $request = $this->addIfExists($request, 'FREIGHTAMT', $level3->shipping->toDecimal());
        $request['ORDERDATE'] = $level3->orderDate->format('mdy');

        $i = 0;
        foreach ($level3->lineItems as $item) {
            ++$i;
            $request = $this->addIfExists($request, 'L_UPC'.$i, $item->productCode);
            $request = $this->addIfExists($request, 'L_DESC'.$i, $item->description);
            $request = $this->addIfExists($request, 'L_COMMCODE'.$i, $item->commodityCode);
            $request = $this->addIfExists($request, 'L_QTY'.$i, $item->quantity);
            $request = $this->addIfExists($request, 'L_COST'.$i, $item->unitCost->toDecimal());
            $request = $this->addIfExists($request, 'L_UOM'.$i, $item->unitOfMeasure);
            $request = $this->addIfExists($request, 'L_DISCOUNT'.$i, $item->discount->toDecimal());
        }

        return $request;
    }

    /**
     * Adds property parameter to request if it exists.
     * Otherwise does nothing.
     */
    private function addIfExists(array $request, string $property, mixed $value): array
    {
        if (isset($value)) {
            $request[$property] = $value;
        }

        return $request;
    }

    /**
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function chargeBankAccount(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, array $documents, string $description): ChargeValueObject
    {
        try {
            $bankAccountValueObject = GatewayHelper::makeAchBankAccount($this->routingNumberLookup, $customer, $account, $parameters, false);
            /** @var BankAccount $bankAccountModel */
            $bankAccountModel = $this->sourceReconciler->reconcile($bankAccountValueObject);
        } catch (ReconciliationException|InvalidBankAccountException $e) {
            throw new ChargeException($e->getMessage());
        }

        return $this->chargeSource($bankAccountModel, $amount, $parameters, $description, $documents);
    }

    /**
     * Adds a bank account to a transaction request as passed.
     */
    private function withBankAccount(PaymentGatewayConfiguration $gatewayConfiguration, array $request, BankAccount $bankAccount): array
    {
        $request['TENDER'] = self::TENDER_BANK_ACCOUNT;
        $request['AUTHTYPE'] = GatewayHelper::secCodeWeb($gatewayConfiguration);

        $request['ABA'] = $bankAccount->routing_number;
        $request['ACCT'] = $bankAccount->account_number;

        // for some reason, FIRSTNAME contains the full name for ACH; prop is named poorly
        $request['FIRSTNAME'] = $bankAccount->account_holder_name;

        $request['ACCTTYPE'] = self::ACCOUNT_TYPE_CHECKING;
        if ('savings' == $bankAccount->type) {
            $request['ACCTTYPE'] = self::ACCOUNT_TYPE_SAVINGS;
        }

        return $request;
    }
}
