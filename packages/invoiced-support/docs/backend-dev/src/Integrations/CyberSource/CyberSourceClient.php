<?php

namespace App\Integrations\CyberSource;

use App\PaymentProcessing\Libs\GatewayLogger;
use SoapClient;
use SoapFault;
use stdClass;

class CyberSourceClient
{
    const ENV_TEST = 'https://ics2wstest.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.142.wsdl';
    const ENV_PRODUCTION = 'https://ics2ws.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.142.wsdl';

    const DECISION_ACCEPT = 'ACCEPT';
    const DECISION_ERROR = 'ERROR';
    const DECISION_REJECT = 'REJECT';

    const ACH_TYPE_CHECKING = 'C';
    const ACH_TYPE_SAVINGS = 'S';

    /**
     * the URL to the WSDL endpoint for the environment we're running in (test or production), as stored in self::ENV_* constants.
     */
    private string $environment;
    private string $merchantId;
    private string $transactionKey;
    private SoapClient $soapClient;
    /**
     * The amount of time in seconds to wait for both a connection and a response. Total potential wait time is this value times 2 (connection + response).
     */
    private int $timeout = 10;
    private string $referenceCode = 'Unknown';
    private array $card = [];
    private array $bankAccount = [];
    private array $billTo = [];
    private string $storedProfile;
    private array $merchantDefinedData = [];
    private GatewayLogger $gatewayLogger;
    /**
     * the retrieved SOAP response, saved immediately after a transaction is run.
     */
    public stdClass $response;

    public static array $avsCodes = [
        'A' => 'Partial match: Street address matches, but 5-digit and 9-digit postal codes do not match.',
        'B' => 'Partial match: Street address matches, but postal code is not verified.',
        'C' => 'No match: Street address and postal code do not match.',
        'D' => 'Match: Street address and postal code match.',
        'E' => 'Invalid: AVS data is invalid or AVS is not allowed for this card type.',
        'F' => 'Partial match: Card member\'s name does not match, but billing postal code matches.',
        'G' => 'Not supported: Non-U.S. issuing bank does not support AVS.',
        'H' => 'Partial match: Card member\'s name does not match, but street address and postal code match.',
        'I' => 'No match: Address not verified.',
        'K' => 'Partial match: Card member\'s name matches, but billing address and billing postal code do not match.',
        'L' => 'Partial match: Card member\'s name and billing postal code match, but billing address does not match.',
        'M' => 'Match: Street address and postal code match.',
        'N' => 'No match: Card member\'s name, street address, or postal code do not match.',
        'O' => 'Partial match: Card member\'s name and billing address match, but billing postal code does not match.',
        'P' => 'Partial match: Postal code matches, but street address not verified.',
        'R' => 'System unavailable.',
        'S' => 'Not supported: U.S. issuing bank does not support AVS.',
        'T' => 'Partial match: Card member\'s name does not match, but street address matches.',
        'U' => 'System unavailable: Address information is unavailable because either the U.S. bank does not support non-U.S. AVS or AVS in a U.S. bank is not functioning properly.',
        'V' => 'Match: Card member\'s name, billing address, and billing postal code match.',
        'W' => 'Partial match: Street address does not match, but 9-digit postal code matches.',
        'X' => 'Match: Street address and 9-digit postal code match.',
        'Y' => 'Match: Street address and 5-digit postal code match.',
        'Z' => 'Partial match: Street address does not match, but 5-digit postal code matches.',
        '1' => 'Not supported: AVS is not supported for this processor or card type.',
        '2' => 'Unrecognized: The processor returned an unrecognized value for the AVS response.',
    ];

    public static array $cvnCodes = [
        'D' => 'The transaction was determined to be suspicious by the issuing bank.',
        'I' => 'The CVN failed the processor\'s data validation check.',
        'M' => 'The CVN matched.',
        'N' => 'The CVN did not match.',
        'P' => 'The CVN was not processed by the processor for an unspecified reason.',
        'S' => 'The CVN is on the card but waqs not included in the request.',
        'U' => 'Card verification is not supported by the issuing bank.',
        'X' => 'Card verification is not supported by the card association.',
        '1' => 'Card verification is not supported for this processor or card type.',
        '2' => 'An unrecognized result code was returned by the processor for the card verification response.',
        '3' => 'No result code was returned by the processor.',
    ];

    public static array $resultCodes = [
        '100' => 'Successful transaction.',
        '101' => 'The request is missing one or more required fields.',
        '102' => 'One or more fields in the request contains invalid data.',
        '110' => 'Only a partial amount was approved.',
        '150' => 'Error: General system failure.',
        '151' => 'Error: The request was received but there was a server timeout.',
        '152' => 'Error: The request was received, but a service did not finish running in time.',
        '200' => 'The authorization request was approved by the issuing bank but declined by CyberSource because it did not pass the Address Verification Service (AVS) check.',
        '201' => 'The issuing bank has questions about the request.',
        '202' => 'Expired card.',
        '203' => 'General decline of the card.',
        '204' => 'Insufficient funds in the account.',
        '205' => 'Stolen or lost card.',
        '207' => 'Issuing bank unavailable.',
        '208' => 'Inactive card or card not authorized for card-not-present transactions.',
        '209' => 'American Express Card Identification Digits (CID) did not match.',
        '210' => 'The card has reached the credit limit.',
        '211' => 'Invalid CVN.',
        '220' => 'The processor declined the request based on a general issue with the customer’s account.',
        '221' => 'The customer matched an entry on the processor\'s negative file.',
        '222' => 'The customer’s bank account is frozen.',
        '223' => 'The customer’s payment or credit has been declined because there is an existing duplicate check, the original transaction was not approved, or a valid authorization could not be located.',
        '230' => 'The authorization request was approved by the issuing bank but declined by CyberSource because it did not pass the CVN check.',
        '231' => 'Invalid credit card number.',
        '232' => 'The card type is not accepted by the payment processor.',
        '233' => 'General decline by the processor.',
        '234' => 'There is a problem with your CyberSource merchant configuration.',
        '235' => 'The requested amount exceeds the originally authorized amount.',
        '236' => 'Processor failure.',
        '237' => 'The authorization has already been reversed.',
        '238' => 'The authorization has already been captured.',
        '239' => 'The requested transaction amount must match the previous transaction amount.',
        '240' => 'The card type sent is invalid or does not correlate with the credit card number.',
        '241' => 'The request ID is invalid.',
        '242' => 'You requested a capture, but there is no corresponding, unused authorization record.',
        '243' => 'The transaction has already been settled or reversed.',
        '246' => 'The capture or credit is not voidable because the capture or credit information has already been submitted to your processor. Or, you requested a void for a type of transaction that cannot be voided.',
        '247' => 'You requested a credit for a capture that was previously voided.',
        '250' => 'Error: The request was received, but there was a timeout at the payment processor.',
        '388' => 'Error: The routing number did not pass verification',
        '520' => 'The authorization request was approved by the issuing bank but declined by CyberSource based on your Smart Authorization settings.',
    ];

    public array $cardTypes = [
        'visa' => '001',
        'mastercard' => '002',
        'american express' => '003',
        'discover' => '004',
        'diners club' => '005',
        'carte blanche' => '006',
        'jcb' => '007',
        'maestro' => '042', // NOTE: this is for non-UK Maestro cards, UK is 024
        // Other types not supported:
        // EnRoute = 014
        // JAL = 021
        // NICOS = 027
        // Delta = 031
        // Visa Electron = 033 (electron is processed as 001 Visa)
        // Dankort = 034
        // Cartes Bancaires = 036
        // Carta Si = 037
        // Encoded account number = 039
        // Hipercard = 050
        // Aura = 051
        // ORICO = 053
        // Elo = 054
        // UnionPay = 062
    ];

    private static array $maskRegexes = [
        '/\<ns2:Password\>(.*)\<\/ns2:Password\>/',
        '/\<ns1:accountNumber\>(.*)\<\/ns1:accountNumber\>/',
        '/\<ns1:cvNumber\>(.*)\<\/ns1:cvNumber\>/',
    ];

    public function __construct(string $merchantId, string $transactionKey, bool $testMode = true)
    {
        $this->merchantId = $merchantId;
        $this->transactionKey = $transactionKey;
        $this->environment = $testMode ? self::ENV_TEST : self::ENV_PRODUCTION;
        $this->soapClient = $this->buildClient();
    }

    public function setReferenceCode(string $code): void
    {
        $this->referenceCode = $code;
    }

    public function setGatewayLogger(GatewayLogger $logger): void
    {
        $this->gatewayLogger = $logger;
    }

    public function card(string $number, string $expirationMonth, string $expirationYear, ?string $cvnCode = null, ?string $cardType = null, ?string $issuingCountry = null): void
    {
        $expirationMonth = str_pad($expirationMonth, 2, '0', STR_PAD_LEFT);

        $this->card = [
            'accountNumber' => $number,
            'expirationMonth' => $expirationMonth,
            'expirationYear' => $expirationYear,
        ];

        // if a cvn code was supplied, use it
        // note that cvIndicator is turned on automatically if we pass in a cvNumber
        if ($cvnCode) {
            $this->card['cvNumber'] = $cvnCode;
        }

        // and if we specified a card type, use that too
        if ($cardType) {
            $cardType = strtolower($cardType);
            if ('maestro' == $cardType && 'GB' == $issuingCountry) {
                $this->card['cardType'] = '024'; // Maestro UK domestic is different than Maestro international
            } elseif (isset($this->cardTypes[$cardType])) {
                $this->card['cardType'] = $this->cardTypes[$cardType];
            }
        }
    }

    public function bankAccount(array $bankAccount): void
    {
        $this->bankAccount = $bankAccount;
    }

    public function storedProfile(string $id): void
    {
        $this->storedProfile = $id;
    }

    public function chargeCard(string $currency, float $amount): stdClass
    {
        $request = $this->buildRequest();

        // we want to perform an authorization
        $request->ccAuthService = new stdClass();
        $request->ccAuthService->run = 'true';        // note that it's textual true so it doesn't get cast as an int

        // and actually charge them
        $request->ccCaptureService = new stdClass();
        $request->ccCaptureService->run = 'true';
        // add billing info to the request
        $request->billTo = $this->create_bill_to();

        // add payment info to the request
        $request->card = $this->create_card();

        if ($this->merchantDefinedData) {
            $request->merchantDefinedData = $this->create_merchant_defined_data();
        }

        $request->purchaseTotals = new stdClass();
        $request->purchaseTotals->currency = strtoupper($currency);
        $request->purchaseTotals->grandTotalAmount = $amount;

        return $this->runTransaction($request);
    }

    public function chargeBankAccount(string $currency, float $amount): stdClass
    {
        $request = $this->buildRequest();

        // we want to perform an authorization
        $request->ecDebitService = new stdClass();
        $request->ecDebitService->run = 'true';        // note that it's textual true so it doesn't get cast as an int

        // add billing info to the request
        $request->billTo = $this->create_bill_to();

        // add payment info to the request
        $request->check = $this->create_bank_account();

        if ($this->merchantDefinedData) {
            $request->merchantDefinedData = $this->create_merchant_defined_data();
        }

        $request->purchaseTotals = new stdClass();
        $request->purchaseTotals->currency = strtoupper($currency);
        $request->purchaseTotals->grandTotalAmount = $amount;

        return $this->runTransaction($request);
    }

    public function chargeStoredCard(string $currency, float $amount): stdClass
    {
        $request = $this->buildRequest();

        // we want to perform an authorization
        $request->ccAuthService = new stdClass();
        $request->ccAuthService->run = 'true';        // note that it's textual true so it doesn't get cast as an int

        // and actually charge them
        $request->ccCaptureService = new stdClass();
        $request->ccCaptureService->run = 'true';

        if ($this->merchantDefinedData) {
            $request->merchantDefinedData = $this->create_merchant_defined_data();
        }

        $request->recurringSubscriptionInfo = new stdClass();
        $request->recurringSubscriptionInfo->subscriptionID = $this->storedProfile;

        $request->purchaseTotals = new stdClass();
        $request->purchaseTotals->currency = strtoupper($currency);
        $request->purchaseTotals->grandTotalAmount = $amount;

        return $this->runTransaction($request);
    }

    public function chargeStoredBankAccount(string $currency, float $amount): stdClass
    {
        $request = $this->buildRequest();

        // we want to perform an authorization
        $request->ecDebitService = new stdClass();
        $request->ecDebitService->run = 'true';        // note that it's textual true so it doesn't get cast as an int

        if ($this->merchantDefinedData) {
            $request->merchantDefinedData = $this->create_merchant_defined_data();
        }

        $request->recurringSubscriptionInfo = new stdClass();
        $request->recurringSubscriptionInfo->subscriptionID = $this->storedProfile;

        $request->purchaseTotals = new stdClass();
        $request->purchaseTotals->currency = strtoupper($currency);
        $request->purchaseTotals->grandTotalAmount = $amount;

        return $this->runTransaction($request);
    }

    public function capture(string $request_token, string $currency, float $amount, ?string $request_id = null): stdClass
    {
        $request = $this->buildRequest();

        $capture_service = new stdClass();
        $capture_service->run = 'true';
        $capture_service->authRequestToken = $request_token;

        if (isset($request_id)) {
            $capture_service->authRequestID = $request_id;
        } else {
            $capture_service->authRequestToken = $request_token;
        }

        $request->ccCaptureService = $capture_service;

        $request->purchaseTotals = new stdClass();
        $request->purchaseTotals->currency = strtoupper($currency);
        $request->purchaseTotals->grandTotalAmount = $amount;

        return $this->runTransaction($request);
    }

    public function authorize(string $currency, float $amount): stdClass
    {
        $request = $this->buildRequest();

        $cc_auth_service = new stdClass();
        $cc_auth_service->run = 'true';
        $request->ccAuthService = $cc_auth_service;

        // add billing info to the request
        $request->billTo = $this->create_bill_to();

        // add credit card info to the request
        $request->card = $this->create_card();

        $request->purchaseTotals = new stdClass();
        $request->purchaseTotals->currency = strtoupper($currency);
        $request->purchaseTotals->grandTotalAmount = $amount;

        // run the authorization
        return $this->runTransaction($request);
    }

    public function reverseAuthorization(string $request_id, string $currency, float $amount): stdClass
    {
        $request = $this->buildRequest();

        $cc_auth_reversal_service = new stdClass();
        $cc_auth_reversal_service->run = 'true';
        $cc_auth_reversal_service->authRequestID = $request_id;
        $request->ccAuthReversalService = $cc_auth_reversal_service;

        $request->purchaseTotals = new stdClass();
        $request->purchaseTotals->currency = strtoupper($currency);
        $request->purchaseTotals->grandTotalAmount = $amount;

        // run the authorization reversal
        return $this->runTransaction($request);
    }

    /**
     * Void a request that has not yet been settled. If it's already settled, you'll have to do a credit instead.
     *
     * It's up to you to figure out if it's been settled or not. May I suggest the reporting API?
     *
     * @param string $request_id the Request ID of the operation you wish to void
     */
    public function void(string $request_id): stdClass
    {
        $request = $this->buildRequest();

        $void_service = new stdClass();
        $void_service->run = 'true';
        $void_service->voidRequestID = $request_id;
        $request->voidService = $void_service;

        return $this->runTransaction($request);
    }

    /**
     * Perform a follow-on credit. This credits back a certain amount, based on a previous Request ID.
     *
     * @param string $request_id the Request ID from a previous charge() or capture() request
     */
    public function credit(string $request_id, string $currency, float $amount): stdClass
    {
        $request = $this->buildRequest();

        // we want to perform an authorization
        $cc_credit_service = new stdClass();
        $cc_credit_service->run = 'true';        // note that it's textual true so it doesn't get cast as an int
        $cc_credit_service->captureRequestID = $request_id;
        $request->ccCreditService = $cc_credit_service;

        $request->purchaseTotals = new stdClass();
        $request->purchaseTotals->currency = strtoupper($currency);
        $request->purchaseTotals->grandTotalAmount = $amount;

        return $this->runTransaction($request);
    }

    public function storeCard(string $currency): stdClass
    {
        $request = $this->buildRequest();

        // we want to create a subscription
        $subscriptionService = new stdClass();
        $subscriptionService->run = 'true';        // note that it's textual true so it doesn't get cast as an int
        $request->paySubscriptionCreateService = $subscriptionService;
        $request->subscription = new stdClass();
        $request->subscription->paymentMethod = 'credit card';
        $request->recurringSubscriptionInfo = new stdClass();
        $request->recurringSubscriptionInfo->amount = 0;
        $request->recurringSubscriptionInfo->frequency = 'on-demand';

        // add billing info to the request
        $request->billTo = $this->create_bill_to();

        // add payment info to the request
        $request->card = $this->create_card();

        $request->purchaseTotals = new stdClass();
        $request->purchaseTotals->currency = strtoupper($currency);

        return $this->runTransaction($request);
    }

    public function storeBankAccount(): stdClass
    {
        $request = $this->buildRequest();

        // we want to create a subscription
        $subscriptionService = new stdClass();
        $subscriptionService->run = 'true';        // note that it's textual true so it doesn't get cast as an int
        $request->paySubscriptionCreateService = $subscriptionService;
        $request->subscription = new stdClass();
        $request->subscription->paymentMethod = 'check';
        $request->recurringSubscriptionInfo = new stdClass();
        $request->recurringSubscriptionInfo->amount = 0;
        $request->recurringSubscriptionInfo->frequency = 'on-demand';

        // add billing info to the request
        $request->billTo = $this->create_bill_to();

        // add payment info to the request
        $request->check = $this->create_bank_account();

        $request->purchaseTotals = new stdClass();
        $request->purchaseTotals->currency = 'USD';

        return $this->runTransaction($request);
    }

    public function deleteProfile(string $id): stdClass
    {
        $request = $this->buildRequest();

        // we want to create a subscription
        $subscriptionService = new stdClass();
        $subscriptionService->run = 'true';        // note that it's textual true so it doesn't get cast as an int
        $request->paySubscriptionDeleteService = $subscriptionService;
        $request->recurringSubscriptionInfo = new stdClass();
        $request->recurringSubscriptionInfo->subscriptionID = $id;

        // add billing info to the request
        $request->billTo = $this->create_bill_to();

        // add payment info to the request
        $request->check = $this->create_bank_account();

        return $this->runTransaction($request);
    }

    /**
     * Factory-pattern method for setting the billing information for this charge.
     *
     * Available fields are:
     *    firstName
     *    lastName
     *    street1
     *    city
     *    state
     *    postalCode
     *    country
     *    email
     *
     * @param array $info An associative array of the fields to set. Note the required fields above.
     */
    public function billTo(array $info): void
    {
        $this->billTo = $info;
    }

    public function merchantDefinedData(array $data): void
    {
        $this->merchantDefinedData = $data;
    }

    protected function buildRequest(): stdClass
    {
        // build the class for the request
        $request = new stdClass();
        $request->merchantID = $this->merchantId;
        $request->merchantReferenceCode = $this->referenceCode;

        // some info CyberSource asks us to add for troubleshooting purposes
        $request->clientLibrary = 'Invoiced';
        $request->clientLibraryVersion = '1.0';
        $request->clientEnvironment = 'Payments';

        return $request;
    }

    public function runTransaction(object $request): stdClass
    {
        try {
            $response = $this->soapClient->runTransaction($request);
            $this->parseResponse($response);
        } catch (SoapFault $e) {
            $this->gatewayLogger->logSoapRequest($this->soapClient, self::$maskRegexes);
            $this->gatewayLogger->logSoapResponse($this->soapClient);

            throw new CyberSourceException(trim($e->getMessage()));
        }

        return $response;
    }

    private function buildClient(): SoapClient
    {
        $context_options = [
            'http' => [
                'timeout' => $this->timeout,
            ],
            'ssl' => [
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            ],
        ];

        $context = stream_context_create($context_options);

        // options we pass into the soap client
        $soap_options = [
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,        // turn on HTTP compression
            'encoding' => 'utf-8',        // set the internal character encoding to avoid random conversions
            'exceptions' => true,        // throw SoapFault exceptions when there is an error
            'connection_timeout' => $this->timeout,
            'stream_context' => $context,
            'user_agent' => 'Invoiced/1.0',
            'trace' => true, // needed to capture requests/responses
        ];

        // if we're in test mode, don't cache the wsdl
        if (self::ENV_TEST == $this->environment) {
            $soap_options['cache_wsdl'] = WSDL_CACHE_NONE;
        }

        // if we're in production mode, cache the wsdl like crazy
        if (self::ENV_PRODUCTION == $this->environment) {
            $soap_options['cache_wsdl'] = WSDL_CACHE_BOTH;
        }

        try {
            // create the soap client
            $soapClient = new SoapClient($this->environment, $soap_options);
        } catch (SoapFault $sf) {
            throw new CyberSourceConnectionException($sf->getMessage(), $sf->getCode());
        }

        // add the wsse token to the soap object, by reference
        $this->addWsseToken($soapClient);

        return $soapClient;
    }

    private function addWsseToken(SoapClient $soap): void
    {
        $wsse_namespace = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $type_namespace = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText';

        $user = new \SoapVar($this->merchantId, XSD_STRING, null, $wsse_namespace, null, $wsse_namespace);
        $pass = new \SoapVar($this->transactionKey, XSD_STRING, null, $type_namespace, null, $wsse_namespace);

        // create the username token container object
        $username_token = new stdClass();
        $username_token->Username = $user;
        $username_token->Password = $pass;

        // convert the username token object into a soap var
        $username_token = new \SoapVar($username_token, SOAP_ENC_OBJECT, null, $wsse_namespace, 'UsernameToken', $wsse_namespace);

        // create the security container object
        $security = new stdClass();
        $security->UsernameToken = $username_token;

        // convert the security container object into a soap var
        $security = new \SoapVar($security, SOAP_ENC_OBJECT, null, $wsse_namespace, 'Security', $wsse_namespace);

        // create the header out of the security soap var
        $header = new \SoapHeader($wsse_namespace, 'Security', $security, true);

        // add the headers to the soap client
        $soap->__setSoapHeaders($header);
    }

    private function parseResponse(stdClass $response): void
    {
        $this->gatewayLogger->logSoapRequest($this->soapClient, self::$maskRegexes);
        $this->gatewayLogger->logSoapResponse($this->soapClient);

        // save the whole response so you can get everything back even on an exception
        $this->response = $response;

        if (self::DECISION_ACCEPT != $response->decision) {
            // customize the error message if the reason indicates a field is missing
            if (101 == $response->reasonCode) {
                if (!isset($response->missingField)) {
                    $msg = 'An unknown error has occurred';
                } elseif (is_array($response->missingField)) {
                    $msg = 'Missing fields: '.implode(', ', $response->missingField);
                } else {
                    $msg = 'Missing field: '.$response->missingField;
                }

                throw new CyberSourceMissingFieldException($msg, 101);
            }

            // customize the error message if the reason code indicates a field is invalid
            if (102 == $response->reasonCode) {
                if (!isset($response->invalidField)) {
                    $msg = 'An unknown error has occurred';
                } elseif (is_array($response->invalidField)) {
                    $msg = 'Invalid fields: '.implode(', ', $response->invalidField);
                } else {
                    $msg = 'Invalid field: '.$response->invalidField;
                }

                throw new CyberSourceInvalidFieldException($msg, 102);
            }

            // otherwise, just throw a generic declined exception
            $msg = 'An unknown error has occurred';
            if (isset(self::$resultCodes[$response->reasonCode])) {
                $msg = self::$resultCodes[$response->reasonCode];
            }

            if (self::DECISION_ERROR == $response->decision) {
                // note that ERROR means some kind of system error or the processor rejected invalid data - it probably doesn't mean the card was actually declined
                throw new CyberSourceErrorException($msg, $response->reasonCode);
            }
            // declined, however, actually means declined. this would be decision 'REJECT', btw.
            throw new CyberSourceDeclinedException($msg, $response->reasonCode);
        }
    }

    private function create_bill_to(): stdClass
    {
        // build the billTo class
        $billTo = new stdClass();

        // add all the bill_to fields
        foreach ($this->billTo as $k => $v) {
            $billTo->$k = $v;
        }

        return $billTo;
    }

    private function create_card(): stdClass
    {
        // build the credit card class
        $card = new stdClass();

        foreach ($this->card as $k => $v) {
            $card->$k = $v;
        }

        return $card;
    }

    private function create_bank_account(): stdClass
    {
        // build the bank account class
        $bankAccount = new stdClass();

        foreach ($this->bankAccount as $k => $v) {
            $bankAccount->$k = $v;
        }

        return $bankAccount;
    }

    private function create_merchant_defined_data(): stdClass
    {
        $data = new stdClass();
        $data->mddField = [];

        foreach ($this->merchantDefinedData as $k => $v) {
            $field = new stdClass();
            $field->id = $k + 1;
            $field->_ = $v;
            $data->mddField[] = $field;
        }

        return $data;
    }
}
