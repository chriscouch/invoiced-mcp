<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Exceptions\TestGatewayCredentialsException;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TestCredentialsInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\HttpClientFactory;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\Level3Data;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

class VantivGateway extends AbstractLegacyGateway implements RefundInterface, TestCredentialsInterface, TransactionStatusInterface
{
    const ID = 'vantiv';

    private const TRANSACTION_URL = 'https://transaction.elementexpress.com/';
    private const SERVICES_URL = 'https://services.elementexpress.com/';

    private const TEST_MODE_TRANSACTION_URL = 'https://certtransaction.elementexpress.com/';
    private const TEST_MODE_SERVICES_URL = 'https://certservices.elementexpress.com/';

    private const APPLICATION_ID = '9404';
    private const TERMINAL_ID = '01';

    const DDA_TYPE_CHECKING = '0';
    const DDA_TYPE_SAVINGS = '1';

    private const RESPONSE_CODE_APPROVED = 0;
    const RESPONSE_CODE_PARTIAL_APPROVED = 5;

    private const TRANSACTION_SUCCESS = '8';

    private const MASK_REGEXES = [
        '/\<AccountToken\>(.*)\<\/AccountToken\>/',
        '/\<AccountNumber\>(.*)\<\/AccountNumber\>/',
        '/\<CardNumber\>(.*)\<\/CardNumber\>/',
        '/\<CVV\>(.*)\<\/CVV\>/',
    ];

    /**
     * Build a failed charge object when these
     * response codes are returned.
     */
    private const FAILED_CHARGE_RESPONSE_CODES = [
        20, // Decline
        21, // Expired card
        22, // Duplicate approved
        23, // Duplicate
        24, // Pick up card
        25, // Referral / Call Issuer
        30, // Balance Not Available
        101, // Invalid data
        104, // Authorization failed
        120, // Out of Balance
    ];

    /**
     * This contains a list of status codes that are
     * considered successful after a charge attempt.
     */
    private const SUCCESSFUL_TRANSACTION_STATUS_CODES = [
        '1', // Approved
        '8', // Success
        '15', // Settled
        '16', // PartialApproved
    ];

    private const AUTHORIZED_TRANSACTION_STATUS_CODES = [
        '5', // Authorized
    ];

    private const FAILED_TRANSACTION_STATUS_CODES = [
        '2', // Declined
        '3', // Duplicate
        '4', // Voided
        '7', // Reversed
        '9', // Returned
        '13', // Error
        '17', // Rejected
    ];

    private const COMMERCIAL_CARD_TYPES = [
        'B', // Business Card
        'D', // Visa Commerce
        'R', // Corporate Card
        'S', // Purchasing Card
    ];

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->account_id)) {
            throw new InvalidGatewayConfigurationException('Missing Vantiv account ID!');
        }

        if (!isset($gatewayConfiguration->credentials->account_token)) {
            throw new InvalidGatewayConfigurationException('Missing Vantiv account token!');
        }

        if (!isset($gatewayConfiguration->credentials->acceptor_id)) {
            throw new InvalidGatewayConfigurationException('Missing Vantiv acceptor ID!');
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

            return $this->chargeBankAccount($bankAccountModel, $account, $amount, $description);
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
            return $this->chargeBankAccount($source, $account, $amount, $description);
        }

        // Configure transaction
        $isCardSale = false;
        if ($source instanceof Card) {
            if (isset($parameters['capture']) && !$parameters['capture']) {
                $sale = new SimpleXMLElement('<CreditCardAuthorization xmlns="https://transaction.elementexpress.com"></CreditCardAuthorization>');
            } else {
                $isCardSale = true;
                $sale = new SimpleXMLElement('<CreditCardSale xmlns="https://transaction.elementexpress.com"></CreditCardSale>');
            }
        } elseif ($source instanceof BankAccount) {
            $sale = new SimpleXMLElement('<CheckSale xmlns="https://transaction.elementexpress.com"></CheckSale>');
        } else {
            throw new ChargeException('Unrecognized payment source type: '.$source->object);
        }

        $gatewayConfiguration = $account->toGatewayConfiguration();
        $this->addCredentials($sale, $gatewayConfiguration);

        $terminal = $this->addTerminal($sale);

        $extParam = $sale->addChild('ExtendedParameters');
        $paymentAccount = $extParam->addChild('PaymentAccount');
        $paymentAccount->addChild('PaymentAccountID', (string) $source->gateway_id);

        if ($source instanceof Card) {
            if (isset($parameters['cvc'])) {
                $card = $sale->addChild('Card');
                $card->addChild('CVV', $parameters['cvc']);
                $terminal->addChild('CVVPresenceCode', '2'); // Provided
            } else {
                $terminal->addChild('CVVPresenceCode', '1'); // Not Provided
            }
        }

        if ($source instanceof BankAccount) {
            $demandDepositAccount = $sale->addChild('DemandDepositAccount');
            if ('savings' == $source->type) {
                $demandDepositAccount->addChild('DDAAccountType', self::DDA_TYPE_SAVINGS);
            } else {
                $demandDepositAccount->addChild('DDAAccountType', self::DDA_TYPE_CHECKING);
            }
        }

        $level3 = GatewayHelper::makeLevel3($documents, $source->customer, $amount);
        $this->addTransaction($sale, $amount, $level3);

        // Send sale to Vantiv
        try {
            $response = $this->performTransactionRequest($gatewayConfiguration, $sale);
        } catch (GuzzleException) {
            throw new ChargeException('An unknown error has occurred when communicating with Worldpay');
        }

        // Parse the response
        $result = $this->parseResponse($response);

        $responseCode = (int) $result->Response->ExpressResponseCode;
        $responseMessage = (string) $result->Response->ExpressResponseMessage;

        // Parse the result
        if (self::RESPONSE_CODE_APPROVED == $responseCode || self::RESPONSE_CODE_PARTIAL_APPROVED == $responseCode) {
            $charge = $this->buildCharge($result, $amount, $source, $description);

            // Send level 3 information to Vantiv
            if ($isCardSale && $this->shouldSendLevel3Data($result)) {
                $this->sendLevel3Data($gatewayConfiguration, $amount, $charge, $level3);
            }

            return $charge;
        }

        // Build failed charge
        if (in_array($responseCode, self::FAILED_CHARGE_RESPONSE_CODES)) {
            $charge = $this->buildCharge($result, $amount, $source, $description);

            throw new ChargeException($responseMessage, $charge);
        }

        if ($responseMessage) {
            throw new ChargeException($responseMessage);
        }

        throw new ChargeException('An unknown error has occurred');
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        // Build the request
        $request = new SimpleXMLElement('<PaymentAccountDelete xmlns="https://services.elementexpress.com"></PaymentAccountDelete>');

        $gatewayConfiguration = $account->toGatewayConfiguration();
        $this->addCredentials($request, $gatewayConfiguration);

        $paymentAccount = $request->addChild('PaymentAccount');
        $paymentAccount->addChild('PaymentAccountID', (string) $source->gateway_id);

        // Send request to Vantiv
        try {
            $response = $this->performServicesRequest($gatewayConfiguration, $request);
        } catch (GuzzleException) {
            throw new PaymentSourceException('An unknown error has occurred when communicating with Worldpay');
        }

        // Parse the response
        $result = $this->parseResponse($response);

        $responseCode = (int) $result->Response->ExpressResponseCode;
        $responseMessage = (string) $result->Response->ExpressResponseMessage;

        // Parse the result
        if (self::RESPONSE_CODE_APPROVED != $responseCode) {
            if ($responseMessage) {
                throw new PaymentSourceException($responseMessage);
            }

            throw new PaymentSourceException('An unknown error has occurred');
        }
    }

    //
    // Refunds
    //

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        $charge = Charge::where('gateway_id', $chargeId)
            ->where('gateway', VantivGateway::ID)
            ->one();

        // check if this is an ACH transaction
        try {
            $this->getTransactionStatus($merchantAccount, $charge);
            // if we made it here then we have an ach transaction
            $wasAch = true;
        } catch (TransactionStatusException $e) {
            // if we made it here then we do not have an ach transaction
            $wasAch = false;
        }

        // Configure transaction
        if ($wasAch) {
            $request = new SimpleXMLElement('<CheckReturn xmlns="https://transaction.elementexpress.com"></CheckReturn>');
        } else {
            $request = new SimpleXMLElement('<CreditCardReturn xmlns="https://transaction.elementexpress.com"></CreditCardReturn>');
        }

        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $this->addCredentials($request, $gatewayConfiguration);

        $terminal = $this->addTerminal($request);

        if (!$wasAch) {
            $terminal->addChild('CVVPresenceCode', '1'); // Not Provided
        }

        $transaction = $request->addChild('Transaction');
        $transaction->addChild('TransactionAmount', number_format($amount->toDecimal(), 2, '.', ''));
        $transaction->addChild('MarketCode', '3'); // ECommerce
        $transaction->addChild('ReferenceNumber', uniqid());
        $transaction->addChild('TicketNumber', uniqid());
        $transaction->addChild('TransactionID', $chargeId);
        $transaction->addChild('DuplicateOverrideFlag', '1');

        // Send sale to Vantiv
        try {
            $response = $this->performTransactionRequest($gatewayConfiguration, $request);
        } catch (GuzzleException $e) {
            throw new RefundException('An unknown error has occurred when communicating with Worldpay');
        }

        // Parse the response
        $result = $this->parseResponse($response);

        $responseCode = (int) $result->Response->ExpressResponseCode;
        $responseMessage = (string) $result->Response->ExpressResponseMessage;

        // Parse the result
        if (self::RESPONSE_CODE_APPROVED == $responseCode || self::RESPONSE_CODE_PARTIAL_APPROVED == $responseCode) {
            return $this->buildRefund($result, $amount);
        }

        // Build failed charge
        if (in_array($responseCode, self::FAILED_CHARGE_RESPONSE_CODES)) {
            throw new RefundException($responseMessage);
        }

        if ($responseMessage) {
            throw new RefundException($responseMessage);
        }

        throw new RefundException('An unknown error has occurred');
    }

    //
    // Transaction Status
    //

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        $chargeId = $charge->gateway_id;
        // Build the request
        $request = new SimpleXMLElement('<CheckQuery xmlns="https://transaction.elementexpress.com"></CheckQuery>');

        $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
        $this->addCredentials($request, $gatewayConfiguration);

        $transaction = $request->addChild('Transaction');
        $transaction->addChild('TransactionID', $chargeId);

        $this->addTerminal($request);

        // Send request to Vantiv
        try {
            $response = $this->performTransactionRequest($gatewayConfiguration, $request);
        } catch (GuzzleException) {
            throw new TransactionStatusException('An unknown error has occurred when communicating with Worldpay');
        }

        // Parse the response
        $result = $this->parseResponse($response);

        $responseCode = (int) $result->Response->ExpressResponseCode;
        $responseMessage = (string) $result->Response->ExpressResponseMessage;

        // Parse the result
        if (self::RESPONSE_CODE_APPROVED == $responseCode) {
            return $this->buildTransactionStatus($result);
        }

        if ($responseMessage) {
            throw new TransactionStatusException($responseMessage);
        }

        throw new TransactionStatusException('An unknown error has occurred');
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        // Build the request
        $request = new SimpleXMLElement('<HealthCheck xmlns="https://transaction.elementexpress.com"></HealthCheck>');

        $this->addCredentials($request, $gatewayConfiguration);

        // Send request to Vantiv
        try {
            $response = $this->performTransactionRequest($gatewayConfiguration, $request);
        } catch (GuzzleException) {
            throw new TestGatewayCredentialsException('An unknown error has occurred when communicating with Worldpay');
        }

        // Parse the response
        $result = $this->parseResponse($response);

        $responseCode = (int) $result->Response->ExpressResponseCode;
        $responseMessage = (string) $result->Response->ExpressResponseMessage;

        if (self::RESPONSE_CODE_APPROVED != $responseCode) {
            throw new TestGatewayCredentialsException($responseMessage);
        }
    }

    //
    // Helpers
    //

    private function addCredentials(SimpleXMLElement $request, PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        $credentialsEl = $request->addChild('Credentials');
        $credentialsEl->addChild('AccountID', $gatewayConfiguration->credentials->account_id);
        $credentialsEl->addChild('AccountToken', $gatewayConfiguration->credentials->account_token);
        $credentialsEl->addChild('AcceptorID', $gatewayConfiguration->credentials->acceptor_id);

        $application = $request->addChild('Application');
        $application->addChild('ApplicationID', self::APPLICATION_ID);
        $application->addChild('ApplicationName', 'Invoiced');
        $application->addChild('ApplicationVersion', '1.0.0');
    }

    public function addTerminal(SimpleXMLElement $request): SimpleXMLElement
    {
        $terminal = $request->addChild('Terminal');
        $terminal->addChild('TerminalID', self::TERMINAL_ID);
        $terminal->addChild('CardholderPresentCode', '7'); // ECommerce
        $terminal->addChild('CardInputCode', '4'); // ManualKeyed
        $terminal->addChild('TerminalCapabilityCode', '5'); // KeyEntered
        $terminal->addChild('TerminalEnvironmentCode', '6'); // ECommerce
        $terminal->addChild('TerminalType', '2'); // ECommerce
        $terminal->addChild('CardPresentCode', '3'); // NotPresent
        $terminal->addChild('MotoECICode', '7'); // NonAuthenticatedSecureECommerceTransaction

        return $terminal;
    }

    /**
     * @throws GuzzleException
     */
    private function performTransactionRequest(PaymentGatewayConfiguration $gatewayConfiguration, SimpleXMLElement $request): ResponseInterface
    {
        // Log the request before sending to Vantiv
        $this->gatewayLogger->logXmlRequest($request, self::MASK_REGEXES);

        return $this->getTransactionClient($gatewayConfiguration)
            ->request('POST', '', ['body' => $request->asXml()]);
    }

    private function getTransactionClient(PaymentGatewayConfiguration $gatewayConfiguration): Client
    {
        $testMode = $gatewayConfiguration->credentials->test_mode;
        if ($testMode) {
            return $this->getClient(self::TEST_MODE_TRANSACTION_URL);
        }

        return $this->getClient(self::TRANSACTION_URL);
    }

    /**
     * @throws GuzzleException
     */
    private function performServicesRequest(PaymentGatewayConfiguration $gatewayConfiguration, SimpleXMLElement $request): ResponseInterface
    {
        // Log the request before sending to Vantiv
        $this->gatewayLogger->logXmlRequest($request, self::MASK_REGEXES);

        return $this->getServicesClient($gatewayConfiguration)
            ->request('POST', '', ['body' => $request->asXml()]);
    }

    private function getServicesClient(PaymentGatewayConfiguration $gatewayConfiguration): Client
    {
        $testMode = $gatewayConfiguration->credentials->test_mode;
        if ($testMode) {
            return $this->getClient(self::TEST_MODE_SERVICES_URL);
        }

        return $this->getClient(self::SERVICES_URL);
    }

    private function getClient(string $url): Client
    {
        $headers = [
            'Content-Type' => 'text/xml; charset=UTF-8',
            'Accept' => 'text/xml',
        ];

        return HttpClientFactory::make($this->gatewayLogger, [
            'base_uri' => $url,
            'headers' => $headers,
        ]);
    }

    /**
     * Parses a response from the Worldpay gateway.
     */
    private function parseResponse(ResponseInterface $response): SimpleXMLElement
    {
        return simplexml_load_string($response->getBody()); /* @phpstan-ignore-line */
    }

    public function buildTransactionStatus(SimpleXMLElement $result): array
    {
        $statusCode = (string) $result->Response->Transaction->TransactionStatusCode;
        $hasFundedDate = !empty($result->Response->Transaction->FundedDate);
        $hasReturnDate = !empty($result->Response->Transaction->ReturnDate);
        $hostResponseMessage = (string) $result->Response->HostResponseMessage;

        // When checking the status of a pending ACH transaction, we only want to
        // consider it successful once the payment has settled. Only then can
        // we know if it was a successful or failed debit.

        // These conditions mean an ACH transaction has been returned (failed):
        // 1) Transaction status code is 8 (success)
        // 2) ReturnDate field is present in Transaction element
        // This must be checked before the successful check

        // These conditions mean an ACH transaction has failed due to a duplicate check:
        // 1) Transaction status code is 8 (success)
        // 2) HostResponseMessage in the Response element equals "B.O.Exception"
        // This must be checked before the successful check

        // These conditions mean an ACH transaction has been canceled (failed):
        // 1) Transaction status code is 8 (success)
        // 2) HostResponseMessage in the Response element equals "Accepted, Cancelled"
        // This must be checked before the successful check

        // These conditions mean an ACH transaction is succeeded:
        // 1) Transaction status code is 8 (success)
        // 2) FundedDate field is present in Transaction element
        //      or HostResponseMessage in the Response element equals "Funded"

        if (self::TRANSACTION_SUCCESS == $statusCode && $hasReturnDate) {
            $status = ChargeValueObject::FAILED;
        } elseif (self::TRANSACTION_SUCCESS == $statusCode && 'Accepted, Cancelled' == $hostResponseMessage) {
            $status = ChargeValueObject::FAILED;
        } elseif (self::TRANSACTION_SUCCESS == $statusCode && 'B.O.Exception' == $hostResponseMessage) {
            $status = ChargeValueObject::FAILED;
        } elseif (self::TRANSACTION_SUCCESS == $statusCode && $hasFundedDate) {
            $status = ChargeValueObject::SUCCEEDED;
        } elseif (in_array($statusCode, self::FAILED_TRANSACTION_STATUS_CODES)) {
            $status = ChargeValueObject::FAILED;
        } else {
            $status = ChargeValueObject::PENDING;
        }

        $statusMessage = (string) $result->Response->Transaction->TransactionStatus;

        return [$status, $hostResponseMessage ?: $statusMessage];
    }

    private function buildRefund(SimpleXMLElement $result, Money $amount): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: (string) $result->Response->Transaction->TransactionID,
            status: RefundValueObject::SUCCEEDED,
            message: (string) $result->Response->ExpressResponseMessage,
        );
    }

    private function buildCharge(SimpleXMLElement $result, Money $amount, PaymentSource $source, string $description): ChargeValueObject
    {
        // Handle partial authorizations.
        // NOTE this is not always set for every transaction. Sometimes
        // it might be 0 for a successful transaction.
        $total = $amount;
        $approvedAmount = (float) $result->Response->Transaction->ApprovedAmount;
        if (property_exists($result->Response->Transaction, 'ApprovedAmount') && $approvedAmount > 0) {
            $total = Money::fromDecimal($amount->currency, $approvedAmount);
        }

        $statusCode = (string) $result->Response->Transaction->TransactionStatusCode;
        if (in_array($statusCode, self::SUCCESSFUL_TRANSACTION_STATUS_CODES)) {
            $status = ChargeValueObject::SUCCEEDED;
        } elseif (in_array($statusCode, self::AUTHORIZED_TRANSACTION_STATUS_CODES)) {
            $status = ChargeValueObject::AUTHORIZED;
        } elseif (in_array($statusCode, self::FAILED_TRANSACTION_STATUS_CODES)) {
            $status = ChargeValueObject::FAILED;
        } else {
            $status = ChargeValueObject::PENDING;
        }

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $total,
            gateway: self::ID,
            gatewayId: (string) $result->Response->Transaction->TransactionID,
            method: '',
            status: $status,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            timestamp: time(),
            failureReason: (string) $result->Response->ExpressResponseMessage,
        );
    }

    private function addTransaction(SimpleXMLElement $request, Money $amount, Level3Data $level3 = null): SimpleXMLElement
    {
        $transaction = $request->addChild('Transaction');
        $transaction->addChild('TransactionAmount', number_format($amount->toDecimal(), 2, '.', ''));
        $transaction->addChild('MarketCode', '3'); // ECommerce
        $transaction->addChild('ReferenceNumber', uniqid());
        $transaction->addChild('TicketNumber', uniqid());
        $transaction->addChild('DuplicateOverrideFlag', '1');

        if ($level3) {
            $transaction->addChild('CommercialCardCustomerCode', substr(htmlspecialchars($level3->poNumber), 0, 25));
            $transaction->addChild('SalesTaxAmount', number_format($level3->salesTax->toDecimal(), 2, '.', ''));
        }

        return $transaction;
    }

    public function shouldSendLevel3Data(SimpleXMLElement $result): bool
    {
        $commercialCardResponseCode = (string) $result->Response->Transaction->CommercialCardResponseCode;

        return in_array($commercialCardResponseCode, self::COMMERCIAL_CARD_TYPES);
    }

    public function addLevel3Data(SimpleXMLElement $request, Level3Data $level3): void
    {
        $extParams = $request->addChild('ExtendedParameters');
        $enhancedData = $extParams->addChild('EnhancedData');
        $enhancedData->addChild('MerchantVATRegistrationNumber', '');
        $enhancedData->addChild('CustomerVATRegistrationNumber', '');
        $enhancedData->addChild('SummaryCommodityCode', substr((string) $level3->summaryCommodityCode, 0, 4));
        $enhancedData->addChild('DiscountAmount', '0.00');
        $enhancedData->addChild('DutyAmount', '0.00');
        $enhancedData->addChild('DestinationZIPCode', $level3->shipTo['postal_code']);
        $enhancedData->addChild('ShipFromZIPCode', $level3->merchantPostalCode);
        $enhancedData->addChild('DestinationCountryCode', $level3->shipTo['country']);
        $enhancedData->addChild('UniqueVATInvoiceReferenceNumber', '');
        $enhancedData->addChild('OrderDate', date('Ymd'));
        $enhancedData->addChild('VATAmount', '0.00');
        $enhancedData->addChild('VATRate', '0.00');
        $enhancedData->addChild('LineItemCount', (string) count($level3->lineItems));

        // add line items
        $lineItems = $enhancedData->addChild('LineItemDetail');
        foreach ($level3->lineItems as $lineItem) {
            $lineItemEl = $lineItems->addChild('LineItem');
            $lineItemEl->addChild('ItemCommodityCode', substr($lineItem->commodityCode, 0, 12));
            $lineItemEl->addChild('ItemDescription', substr(htmlspecialchars($lineItem->description), 0, 35));
            $lineItemEl->addChild('ProductCode', substr($lineItem->productCode, 0, 12));
            $lineItemEl->addChild('Quantity', number_format($lineItem->quantity, 2, '.', ''));
            $lineItemEl->addChild('UnitOfMeasure', substr($lineItem->unitOfMeasure, 0, 12));
            $lineItemEl->addChild('UnitCost', number_format($lineItem->unitCost->toDecimal(), 2, '.', ''));
            $lineItemEl->addChild('LineItemVATAmount', '0.00');
            $lineItemEl->addChild('LineItemVATRate', '0');
            $lineItemEl->addChild('LineItemDiscountAmount', number_format($lineItem->discount->toDecimal(), 2, '.', ''));
            $lineItemEl->addChild('LineItemTotalAmount', number_format($lineItem->total->toDecimal(), 2, '.', ''));
            $lineItemEl->addChild('AlternateTaxIdentifier', '');
            $lineItemEl->addChild('VATType', '');
            $lineItemEl->addChild('DiscountCode', '');
            $lineItemEl->addChild('NetGrossCode', '');
            $lineItemEl->addChild('ExtendedItemAmount', '');
            $lineItemEl->addChild('DebitCreditCode', '');
            $lineItemEl->addChild('ItemDiscountRate', '');
        }
    }

    /**
     * Sends level 3 data to Vantiv.
     *
     * NOTE: this endpoint makes a best effort attempt and
     * should not throw an exception if sending in level 3
     * data fails.
     */
    public function sendLevel3Data(PaymentGatewayConfiguration $gatewayConfiguration, Money $amount, ChargeValueObject $charge, Level3Data $level3): void
    {
        // Build the request
        $request = new SimpleXMLElement('<CreditCardAdjustment xmlns="https://transaction.elementexpress.com"></CreditCardAdjustment>');

        $this->addCredentials($request, $gatewayConfiguration);
        $this->addTerminal($request);
        $transaction = $this->addTransaction($request, $amount);
        $transaction->addChild('TransactionID', $charge->gatewayId);

        $this->addLevel3Data($request, $level3);

        // Send adjustment to Vantiv
        try {
            $this->performTransactionRequest($gatewayConfiguration, $request);
            // not parsing the response for level 3 data
        } catch (GuzzleException) {
            // this endpoint should not throw any exceptions
        }
    }

    /**
     * @throws ChargeException
     */
    private function chargeBankAccount(BankAccount $bankAccount, MerchantAccount $account, Money $amount, string $description): ChargeValueObject
    {
        // Configure transaction
        $sale = new SimpleXMLElement('<CheckSale xmlns="https://transaction.elementexpress.com"></CheckSale>');

        $gatewayConfiguration = $account->toGatewayConfiguration();
        $this->addCredentials($sale, $gatewayConfiguration);

        $this->addTerminal($sale);

        $demandDepositAccount = $this->addBankAccount($sale, $bankAccount);

        if (BankAccountValueObject::TYPE_SAVINGS == $bankAccount->type) {
            $demandDepositAccount->addChild('DDAAccountType', self::DDA_TYPE_SAVINGS);
        } else {
            $demandDepositAccount->addChild('DDAAccountType', self::DDA_TYPE_CHECKING);
        }

        $addressEl = $sale->addChild('Address');
        $addressEl->addChild('BillingName', htmlspecialchars((string) $bankAccount->account_holder_name));

        $this->addTransaction($sale, $amount);

        // Send sale to Vantiv
        try {
            $response = $this->performTransactionRequest($gatewayConfiguration, $sale);
        } catch (GuzzleException) {
            throw new ChargeException('An unknown error has occurred when communicating with Worldpay');
        }

        // Parse the response
        $result = $this->parseResponse($response);

        $responseCode = (int) $result->Response->ExpressResponseCode;
        $responseMessage = (string) $result->Response->ExpressResponseMessage;

        // Parse the result
        if (self::RESPONSE_CODE_APPROVED == $responseCode || self::RESPONSE_CODE_PARTIAL_APPROVED == $responseCode) {
            return $this->buildCharge($result, $amount, $bankAccount, $description);
        }

        // Build failed charge
        if (in_array($responseCode, self::FAILED_CHARGE_RESPONSE_CODES)) {
            $charge = $this->buildCharge($result, $amount, $bankAccount, $description);

            throw new ChargeException($responseMessage, $charge);
        }

        if ($responseMessage) {
            throw new ChargeException($responseMessage);
        }

        throw new ChargeException('An unknown error has occurred');
    }

    private function addBankAccount(SimpleXMLElement $request, BankAccount $bankAccount): SimpleXMLElement
    {
        $demandDepositAccount = $request->addChild('DemandDepositAccount');
        $demandDepositAccount->addChild('AccountNumber', $bankAccount->account_number);
        $demandDepositAccount->addChild('RoutingNumber', $bankAccount->routing_number);

        return $demandDepositAccount;
    }
}
