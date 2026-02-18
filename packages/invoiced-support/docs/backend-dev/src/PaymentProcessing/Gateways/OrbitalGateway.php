<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Exceptions\TestGatewayCredentialsException;
use App\PaymentProcessing\Exceptions\VoidAlreadySettledException;
use App\PaymentProcessing\Exceptions\VoidException;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\VoidInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\HttpClientFactory;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SimpleXMLElement;

class OrbitalGateway extends AbstractLegacyGateway implements RefundInterface, VoidInterface
{
    const ID = 'orbital';

    private const LIVE_URL = 'https://orbital1.chasepaymentech.com/authorize';
    private const LIVE_FALLBACK_URL = 'https://orbital2.chasepaymentech.com/authorize';
    private const SANDBOX_URL = 'https://orbitalvar1.chasepaymentech.com/authorize';
    private const SANDBOX_FALLBACK_URL = 'https://orbitalvar2.chasepaymentech.com/authorize';

    private const BIN_STRATUS = '000001'; // Stratus Platform
    private const BIN_TANDEM = '000002'; // Tandem / PNS Platform

    private const TERMINAL_ID_STRATUS = '001'; // Stratus Platform

    private const ORDER_TYPE_AUTHORIZE = 'A';
    private const ORDER_TYPE_SALE = 'AC';
    private const ORDER_TYPE_REFUND = 'R';
    private const INDUSTRY_TYPE_ECOMMERCE = 'EC';

    // Subsequent transactions use CUSE because this is a
    // customer selecting a stored card to pay an invoice
    private const MIT_SUBSEQUENT_MESSAGE_TYPE = 'CUSE';

    private const PROFILE_SUCCESS = '0';
    private const NEWORDER_SUCCESS = '00';

    private const ACH_TYPE_CHECKING = 'C';
    private const ACH_TYPE_SAVINGS = 'S';

    private const MASK_REGEXES = [
        '/\<OrbitalConnectionPassword\>(.*)\<\/OrbitalConnectionPassword\>/',
        '/\<CheckDDA\>(.*)\<\/CheckDDA\>/',
        '/\<AccountNum\>(.*)\<\/AccountNum\>/',
        '/\<CardSecVal\>(.*)\<\/CardSecVal\>/',
        '/\<CCAccountNum\>(.*)\<\/CCAccountNum\>/',
        '/\<ECPAccountDDA\>(.*)\<\/ECPAccountDDA\>/',
    ];

    /**
     * These are Process Status codes that indicate a void
     * failed because the transaction is settled.
     */
    private const VOID_ALREADY_SETTLED_CODES = [
        '327', // This transaction cannot be reversed.
        '882', // This transaction is locked down. You cannot mark or unmark it. (INVD-2225)
    ];

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->merchant_id)) {
            throw new InvalidGatewayConfigurationException('Missing Chase Paymentech merchant ID!');
        }

        if (!isset($gatewayConfiguration->credentials->username)) {
            throw new InvalidGatewayConfigurationException('Missing Chase Paymentech Orbital username!');
        }

        if (!isset($gatewayConfiguration->credentials->password)) {
            throw new InvalidGatewayConfigurationException('Missing Chase Paymentech Orbital password!');
        }

        if (isset($gatewayConfiguration->credentials->bin)) {
            if (!in_array($gatewayConfiguration->credentials->bin, [self::BIN_STRATUS, self::BIN_TANDEM])) {
                throw new InvalidGatewayConfigurationException('Invalid Chase Paymentech Orbital BIN: '.$gatewayConfiguration->credentials->bin);
            }

            if (self::BIN_TANDEM == $gatewayConfiguration->credentials->bin && !isset($gatewayConfiguration->credentials->terminal_id)) {
                throw new InvalidGatewayConfigurationException('Missing Chase Paymentech Orbital terminal ID!');
            }
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
        $request = $this->buildChargeSource($gatewayConfiguration, $amount, $source, $documents, $parameters);

        // perform the call on Orbital
        try {
            $result = $this->performRequest($gatewayConfiguration, $request);
        } catch (GuzzleException|RuntimeException) {
            throw new ChargeException('An unknown error has occurred when communicating with Chase Orbital');
        }

        // parse the response
        if (isset($result->QuickResp)) {
            throw new ChargeException((string) $result->QuickResp->StatusMsg);
        }

        $newOrderResp = $result->NewOrderResp;
        if (self::NEWORDER_SUCCESS != (string) $newOrderResp->RespCode) {
            $failed = $this->buildCharge($newOrderResp, $source, $amount, ChargeValueObject::FAILED, $description);

            throw new ChargeException((string) $newOrderResp->StatusMsg, $failed);
        }

        return $this->buildCharge($newOrderResp, $source, $amount, ChargeValueObject::SUCCEEDED, $description);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        // build the request
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $request = $this->buildDelete($gatewayConfiguration, $source);

        // perform the call on Orbital
        try {
            $this->performRequest($gatewayConfiguration, $request);
        } catch (GuzzleException|RuntimeException) {
            throw new PaymentSourceException('An unknown error has occurred when communicating with Chase Orbital');
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

            return new RefundValueObject(
                amount: $amount,
                gateway: self::ID,
                gatewayId: $chargeId,
                status: RefundValueObject::SUCCEEDED,
            );
        } catch (VoidAlreadySettledException) {
            // do nothing
        } catch (VoidException $e) {
            throw new RefundException($e->getMessage());
        }

        return $this->credit($merchantAccount, $chargeId, $amount);
    }

    public function void(MerchantAccount $merchantAccount, string $chargeId): void
    {
        // build the request
        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $request = $this->buildVoid($gatewayConfiguration, $chargeId);

        // perform the call on Orbital
        try {
            $result = $this->performRequest($gatewayConfiguration, $request);
        } catch (GuzzleException|RuntimeException) {
            throw new VoidException('An unknown error has occurred when communicating with Chase Orbital');
        }

        // parse the response
        // Voids can produce a failed status code in the QuickResp or ReversalResp
        // element depending on the nature of the failure.
        if (isset($result->QuickResp)) {
            // If the payment has already settled then a void is not
            // possible. We throw a special exception type for that
            // scenario in order for the caller to perform a refund.
            $quickResp = $result->QuickResp;
            $procStatus = (string) $quickResp->ProcStatus;
            if (in_array($procStatus, self::VOID_ALREADY_SETTLED_CODES)) {
                throw new VoidAlreadySettledException((string) $quickResp->StatusMsg);
            }

            throw new VoidException((string) $quickResp->StatusMsg);
        }

        // If the payment has already settled then a void is not
        // possible. We throw a special exception type for that
        // scenario in order for the caller to perform a refund.
        $reversal = $result->ReversalResp;
        $procStatus = (string) $reversal->ProcStatus;
        if (in_array($procStatus, self::VOID_ALREADY_SETTLED_CODES)) {
            throw new VoidAlreadySettledException((string) $reversal->StatusMsg);
        }

        if (self::PROFILE_SUCCESS != $procStatus) {
            throw new VoidException((string) $reversal->StatusMsg);
        }
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        // build the request
        $request = $this->buildTest($gatewayConfiguration);

        // perform the call on Orbital
        try {
            $this->performRequest($gatewayConfiguration, $request);
        } catch (ClientException) {
            throw new TestGatewayCredentialsException('Invalid Credentials');
        } catch (GuzzleException|RuntimeException) {
            throw new TestGatewayCredentialsException('An unknown error has occurred when communicating with Chase Orbital');
        }
    }

    //
    // Helpers
    //

    public function getClient(PaymentGatewayConfiguration $gatewayConfiguration, bool $isFailover): Client
    {
        $testMode = (isset($gatewayConfiguration->credentials->test_mode)) ? (bool) $gatewayConfiguration->credentials->test_mode : false;

        $url = $isFailover ? self::LIVE_FALLBACK_URL : self::LIVE_URL;
        if ($testMode) {
            $url = $isFailover ? self::SANDBOX_FALLBACK_URL : self::SANDBOX_URL;
        }

        $headers = [
            'Content-Type' => 'application/PTI83',
            'Content-Transfer-Encoding' => 'text',
            'Request-Number' => '1',
            'Document-Type' => 'Request',
            'MIME-Version' => '1.1',
        ];

        return HttpClientFactory::make($this->gatewayLogger, [
            'base_uri' => $url,
            'headers' => $headers,
        ]);
    }

    /**
     * Makes a request to Orbital.
     *
     * @throws RuntimeException|GuzzleException
     */
    private function performRequest(PaymentGatewayConfiguration $gatewayConfiguration, SimpleXMLElement $request, bool $isFailover = false): SimpleXMLElement
    {
        $this->gatewayLogger->logXmlRequest($request, self::MASK_REGEXES);

        $client = $this->getClient($gatewayConfiguration, $isFailover);

        try {
            $response = $client->request('POST', '', ['body' => $request->asXml()]);

            return $this->parseResponse($response);
        } catch (GuzzleException $e) {
            if ($isFailover) {
                throw $e;
            }

            return $this->performRequest($gatewayConfiguration, $request, true);
        }
    }

    /**
     * Parses a response from the Orbital gateway.
     */
    private function parseResponse(ResponseInterface $response): SimpleXMLElement
    {
        $result = simplexml_load_string((string) $response->getBody());
        if (!($result instanceof SimpleXMLElement)) {
            throw new RuntimeException('Could not parse Orbital XML response');
        }

        return $result;
    }

    //
    // XML Construction
    //

    /**
     * Gets the BIN ID for the given credentials.
     */
    private function getBin(object $credentials): string
    {
        if (isset($credentials->bin)) {
            return $credentials->bin;
        }

        return self::BIN_STRATUS;
    }

    /**
     * Gets the terminal ID for the given credentials.
     */
    private function getTerminalId(string $bin, object $credentials): string
    {
        if (self::BIN_TANDEM == $bin) {
            return $credentials->terminal_id;
        }

        return self::TERMINAL_ID_STRATUS;
    }

    /**
     * Build start of request.
     */
    public function buildRequest(): SimpleXMLElement
    {
        return new SimpleXMLElement('<Request></Request>');
    }

    /**
     * Build xml schema for delete source.
     */
    public function buildDelete(PaymentGatewayConfiguration $gatewayConfiguration, PaymentSource $source): SimpleXMLElement
    {
        $request = $this->startProfileAdd($gatewayConfiguration);
        $profile = $request->Profile;

        $sourceId = $this->deserializeSourceId((string) $source->gateway_id);
        $profile->addChild('CustomerRefNum', $sourceId['id']);
        $profile->addChild('CustomerProfileAction', 'D');
        $profile->addChild('Status', 'A');

        return $request;
    }

    /**
     * Build xml for testing credentials.
     */
    public function buildTest(PaymentGatewayConfiguration $gatewayConfiguration): SimpleXMLElement
    {
        $bin = $this->getBin($gatewayConfiguration->credentials);
        $terminalId = $this->getTerminalId($bin, $gatewayConfiguration->credentials);

        $request = $this->buildRequest();
        $inquiry = $request->addChild('Inquiry');
        $inquiry->addChild('OrbitalConnectionUsername', $gatewayConfiguration->credentials->username);
        $inquiry->addChild('OrbitalConnectionPassword', $gatewayConfiguration->credentials->password);

        $inquiry->addChild('BIN', $bin);
        $inquiry->addChild('MerchantID', $gatewayConfiguration->credentials->merchant_id);
        $inquiry->addChild('TerminalID', $terminalId);
        $inquiry->addChild('OrderID', $this->randomInt());
        $inquiry->addChild('InquiryRetryNumber', $this->randomInt());

        return $request;
    }

    /**
     * Build authentication xml schema for Profile Add Requests.
     */
    public function startProfileAdd(PaymentGatewayConfiguration $gatewayConfiguration): SimpleXMLElement
    {
        $bin = $this->getBin($gatewayConfiguration->credentials);

        $request = $this->buildRequest();
        $order = $request->addChild('Profile');
        $order->addChild('OrbitalConnectionUsername', $gatewayConfiguration->credentials->username);
        $order->addChild('OrbitalConnectionPassword', $gatewayConfiguration->credentials->password);
        $order->addChild('CustomerBin', $bin);
        $order->addChild('CustomerMerchantID', $gatewayConfiguration->credentials->merchant_id);

        return $request;
    }

    /**
     * Generates a random integer for use in
     * request fields, i.e. OrderID.
     */
    private function randomInt(): string
    {
        return (string) random_int(1, PHP_INT_MAX);
    }

    /**
     * Deserializes a source ID which might have a "|" character
     * to store additional attributes. The extra attributes are
     * not used currently.
     */
    private function deserializeSourceId(string $id): array
    {
        $sourceParts = explode('|', $id);

        return [
            'id' => $sourceParts[0],
        ];
    }

    /**
     * Build XML schema for void (also known as reversal).
     */
    public function buildVoid(PaymentGatewayConfiguration $gatewayConfiguration, string $chargeId): SimpleXMLElement
    {
        $bin = $this->getBin($gatewayConfiguration->credentials);
        $terminalId = $this->getTerminalId($bin, $gatewayConfiguration->credentials);

        $request = $this->buildRequest();
        $refund = $request->addChild('Reversal');
        $refund->addChild('OrbitalConnectionUsername', $gatewayConfiguration->credentials->username);
        $refund->addChild('OrbitalConnectionPassword', $gatewayConfiguration->credentials->password);

        $refund->addChild('TxRefNum', $chargeId);
        $refund->addChild('OrderID', $this->randomInt());
        $refund->addChild('BIN', $bin);
        $refund->addChild('MerchantID', $gatewayConfiguration->credentials->merchant_id);
        $refund->addChild('TerminalID', $terminalId);

        return $request;
    }

    /**
     * Performs a refund by TxRefNum request.
     */
    private function credit(MerchantAccount $account, string $chargeId, Money $amount): RefundValueObject
    {
        // build the request
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $request = $this->buildRefund($gatewayConfiguration, $chargeId, $amount);

        // perform the call on Orbital
        try {
            $result = $this->performRequest($gatewayConfiguration, $request);
        } catch (GuzzleException|RuntimeException $e) {
            throw new RefundException('An unknown error has occurred when communicating with Chase Orbital');
        }

        // parse the response
        if (isset($result->QuickResp)) {
            throw new RefundException((string) $result->QuickResp->StatusMsg);
        }

        $refund = $result->NewOrderResp;
        if (self::PROFILE_SUCCESS != (string) $refund->ProcStatus) {
            throw new RefundException((string) $refund->StatusMsg);
        }

        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: (string) $refund->TxRefNum,
            status: RefundValueObject::SUCCEEDED,
        );
    }

    /**
     * Build XML schema for refund.
     */
    public function buildRefund(PaymentGatewayConfiguration $gatewayConfiguration, string $chargeId, Money $amount): SimpleXMLElement
    {
        $request = $this->startOfNewOrder($gatewayConfiguration, self::ORDER_TYPE_REFUND);
        $order = $request->NewOrder;
        $this->addCurrency($order, $amount->currency);
        $order->addChild('OrderID', $this->randomInt());
        $order->addChild('Amount', (string) $amount->amount);
        $order->addChild('TxRefNum', $chargeId);

        return $request;
    }

    /**
     * Build xml schema for authentication for new order requests.
     */
    private function startOfNewOrder(PaymentGatewayConfiguration $gatewayConfiguration, string $messageType = self::ORDER_TYPE_SALE): SimpleXMLElement
    {
        $bin = $this->getBin($gatewayConfiguration->credentials);
        $terminalId = $this->getTerminalId($bin, $gatewayConfiguration->credentials);

        $request = $this->buildRequest();
        $order = $request->addChild('NewOrder');
        $order->addChild('OrbitalConnectionUsername', $gatewayConfiguration->credentials->username);
        $order->addChild('OrbitalConnectionPassword', $gatewayConfiguration->credentials->password);
        $order->addChild('IndustryType', self::INDUSTRY_TYPE_ECOMMERCE);
        $order->addChild('MessageType', $messageType);
        $order->addChild('BIN', $bin);
        $order->addChild('MerchantID', $gatewayConfiguration->credentials->merchant_id);
        $order->addChild('TerminalID', $terminalId);

        return $request;
    }

    private function addCurrency(SimpleXMLElement $order, string $currency): void
    {
        $order->addChild('CurrencyCode', Currencies::NUMERIC_CODES[$currency]);
        $order->addChild('CurrencyExponent', Currencies::EXPONENTS[$currency]);
    }

    /**
     * Build xml schema for charging a source that is vaulted in orbital.
     *
     * @param ReceivableDocument[] $documents
     */
    public function buildChargeSource(PaymentGatewayConfiguration $gatewayConfiguration, Money $amount, PaymentSource $source, array $documents, array $parameters): SimpleXMLElement
    {
        $isSale = $parameters['capture'] ?? true;
        $messageType = $isSale ? self::ORDER_TYPE_SALE : self::ORDER_TYPE_AUTHORIZE;
        $request = $this->startOfNewOrder($gatewayConfiguration, $messageType);
        $order = $request->NewOrder;

        // Currency
        $this->addCurrency($order, $amount->currency);

        // billing address
        $customer = $source->customer;
        if ($customer->address1 && $customer->postal_code && $customer->city) {
            $order->addChild('AVSzip', substr($this->alphanumOnly($customer->postal_code), 0, 10));
            $order->addChild('AVSaddress1', substr($this->alphanumOnly($customer->address1), 0, 30));
            if ($customer->address2) {
                $order->addChild('AVSaddress2', substr($this->alphanumOnly($customer->address2), 0, 30));
            }
            $order->addChild('AVScity', substr($this->alphanumOnly($customer->city), 0, 20));
            if ($customer->state) {
                $order->addChild('AVSstate', substr($this->alphanumOnly($customer->state), 0, 2));
            }
        }

        $sourceId = $this->deserializeSourceId((string) $source->gateway_id);
        $order->addChild('CustomerRefNum', $sourceId['id']);

        // set the required order ID
        $this->addOrderId($order, $documents);

        // Amount
        $order->addChild('Amount', (string) $amount->amount);

        // MIT / CIT Framework (saved cards only)
        if ($source instanceof Card) {
            $order->addChild('MITMsgType', self::MIT_SUBSEQUENT_MESSAGE_TYPE);
            $order->addChild('MITStoredCredentialInd', 'Y');
        }

        return $request;
    }

    /**
     * Strips all non-alphanumeric characters.
     */
    private function alphanumOnly(?string $input): string
    {
        return (string) preg_replace('/[^A-Za-z0-9 ]/', '', (string) $input);
    }

    /**
     * Builds a charge object from an Orbital transaction response.
     */
    private function buildCharge(SimpleXMLElement $result, PaymentSource $source, Money $amount, string $status, string $description): ChargeValueObject
    {
        // Check if there was a partial authorization
        $total = $amount;
        if (isset($result->redeemedAmount)) {
            $total = Money::fromDecimal($amount->currency, (float) $result->redeemedAmount);
        }

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $total,
            gateway: self::ID,
            gatewayId: (string) $result->TxRefNum,
            method: '',
            status: $status,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: (string) $result->StatusMsg,
        );
    }

    /**
     * @param ReceivableDocument[] $documents
     */
    private function addOrderId(SimpleXMLElement $order, array $documents): void
    {
        if (count($documents) > 0) {
            $order->addChild('OrderID', $this->alphanumOnly((string) $documents[0]->id));
        } else {
            $order->addChild('OrderID', $this->randomInt());
        }
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
        $request = $this->buildChargeBankAccount($gatewayConfiguration, $amount, $bankAccount, $parameters, $documents);

        // perform the call on Orbital
        try {
            $result = $this->performRequest($gatewayConfiguration, $request);
        } catch (GuzzleException|RuntimeException) {
            throw new ChargeException('An unknown error has occurred when communicating with Chase Orbital');
        }

        // parse the response
        if (isset($result->QuickResp)) {
            throw new ChargeException((string) $result->QuickResp->StatusMsg);
        }

        $newOrderResp = $result->NewOrderResp;
        if (self::NEWORDER_SUCCESS != (string) $newOrderResp->RespCode) {
            $failed = $this->buildCharge($newOrderResp, $bankAccount, $amount, ChargeValueObject::FAILED, $description);

            throw new ChargeException((string) $newOrderResp->StatusMsg, $failed);
        }

        return $this->buildCharge($newOrderResp, $bankAccount, $amount, ChargeValueObject::SUCCEEDED, $description);
    }

    /**
     * Build xml schema for charging a bank account.
     *
     * @param ReceivableDocument[] $documents
     */
    public function buildChargeBankAccount(PaymentGatewayConfiguration $gatewayConfiguration, Money $amount, BankAccount $bankAccount, array $parameters, array $documents): SimpleXMLElement
    {
        $isSale = $parameters['capture'] ?? true;
        $messageType = $isSale ? self::ORDER_TYPE_SALE : self::ORDER_TYPE_AUTHORIZE;
        $request = $this->startOfNewOrder($gatewayConfiguration, $messageType);
        $order = $request->NewOrder;

        // Card Brand
        $order->addChild('CardBrand', 'EC');

        // Currency
        $this->addCurrency($order, $amount->currency);

        // Bank Account Information
        $order->addChild('BCRtNum', $bankAccount->routing_number);
        $order->addChild('CheckDDA', $bankAccount->account_number);
        $type = self::ACH_TYPE_CHECKING;
        if (BankAccountValueObject::TYPE_SAVINGS == $bankAccount->type) {
            $type = self::ACH_TYPE_SAVINGS;
        }
        $order->addChild('BankAccountType', $type);
        $order->addChild('ECPAuthMethod', 'I');
        $order->addChild('BankPmtDelv', 'B');

        // Billing Address
        $order->addChild('AVSname', substr($this->alphanumOnly($bankAccount->account_holder_name), 0, 30));

        // This will create a customer profile from the charge
        $isProfile = $parameters['profile'] ?? false;
        if ($isProfile) {
            $this->addCustomerProfileFlag($order);
        }

        // Order ID
        $this->addOrderId($order, $documents);

        // Amount
        $order->addChild('Amount', (string) $amount->amount);

        return $request;
    }

    private function addCustomerProfileFlag(SimpleXMLElement $order): void
    {
        $order->addChild('CustomerProfileFromOrderInd', 'A');
        $order->addChild('CustomerProfileOrderOverrideInd', 'NO');
    }
}
