<?php

namespace App\Integrations\Adyen;

use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Libs\RoutingNumberLookup;
use App\PaymentProcessing\ValueObjects\DisputeDocument;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\RequestException;
use mikehaertl\tmp\File as TmpFile;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AdyenClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const array MASKED_REQUEST_PARAMETERS = [
        'x-API-key',
        'Authorization',
        'content',
        'holderName',
        'encryptedCardNumber',
        'encryptedExpiryMonth',
        'encryptedExpiryYear',
        'encryptedSecurityCode',
        'checkoutAttemptId',
        'clientData',
        'type',
        'bankAccountNumber',
        'bankAccountType',
        'bankLocationId',
        'ownerName',
        'street',
        'houseNumberOrName',
        'postalCode',
        'city',
        'stateOrProvince',
        'country',
        'shopperReference',
        'returnUrl',
        'shopperEmail',
        'enhancedSchemeData.customerReference',
        'enhancedSchemeData.destinationCountryCode',
        'enhancedSchemeData.destinationPostalCode',
        'enhancedSchemeData.destinationStateProvinceCode',
        'enhancedSchemeData.dutyAmount',
        'enhancedSchemeData.freightAmount',
        'enhancedSchemeData.orderDate',
        'enhancedSchemeData.shipFromPostalCode',
        'enhancedSchemeData.totalTaxAmount',
        'enhancedSchemeData.itemDetailLine1.commodityCode',
        'enhancedSchemeData.itemDetailLine1.description',
        'enhancedSchemeData.itemDetailLine1.discountAmount',
        'enhancedSchemeData.itemDetailLine1.productCode',
        'enhancedSchemeData.itemDetailLine1.quantity',
        'enhancedSchemeData.itemDetailLine1.totalAmount',
        'enhancedSchemeData.itemDetailLine1.unitOfMeasure',
        'enhancedSchemeData.itemDetailLine1.unitPrice',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly GatewayLogger $gatewayLogger,
        private readonly string $apiKey,
        private readonly string $bclApiKey,
        private readonly string $lemApiKey,
        private readonly string $reportApiKey,
        private readonly string $settlementReportApiKey,
        private readonly bool $adyenLiveMode,
        private RoutingNumberLookup $routingNumberLookup,
    ) {
    }

    /**
     * @throws IntegrationApiException
     */
    public function getMerchantAccount(string $id): array
    {
        return $this->makeManagementRequest('GET', '/v3/merchants/'.$id);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getMerchantAccountPayoutSettings(string $id): array
    {
        return $this->makeManagementRequest('GET', '/v3/merchants/'.$id.'/payoutSettings');
    }

    /**
     * @throws IntegrationApiException
     */
    public function createLegalEntity(array $params): array
    {
        return $this->makeKycRequest('POST', '/lem/v3/legalEntities', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getLegalEntity(string $id): array
    {
        return $this->makeKycRequest('GET', '/lem/v3/legalEntities/'.$id, []);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getBusinessLines(string $legalEntityId): array
    {
        return $this->makeKycRequest('GET', '/lem/v3/legalEntities/'.$legalEntityId.'/businessLines', []);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createOnboardingLink(string $id, array $params): array
    {
        return $this->makeKycRequest('POST', '/lem/v3/legalEntities/'.$id.'/onboardingLinks', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createBusinessLine(array $params): array
    {
        return $this->makeKycRequest('POST', '/lem/v3/businessLines', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createSplitConfiguration(string $merchantId, array $params): array
    {
        return $this->makeManagementRequest('POST', '/v3/merchants/'.$merchantId.'/splitConfigurations', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createStore(array $params): array
    {
        return $this->makeManagementRequest('POST', '/v3/stores', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getStore(string $id): array
    {
        return $this->makeManagementRequest('GET', '/v3/stores/'.$id);
    }

    /**
     * @throws IntegrationApiException
     */
    public function updateStore(string $splitId, array $params): array
    {
        return $this->makeManagementRequest('PATCH', '/v3/stores/'.$splitId, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createAllowedOrigin(string $domain): array
    {
        $params = [
            'domain' => $domain,
        ];

        return $this->makeManagementRequest('POST', '/v3/me/allowedOrigins', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createPaymentMethodSetting(string $merchantId, array $params): array
    {
        return $this->makeManagementRequest('POST', '/v3/merchants/'.$merchantId.'/paymentMethodSettings', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getPaymentMethodSettings(string $merchantId, array $params): array
    {
        return $this->makeManagementRequest('GET', '/v3/merchants/'.$merchantId.'/paymentMethodSettings', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function updatePaymentMethodSettings(string $merchantId, string $paymentMethodId, array $params): array
    {
        return $this->makeManagementRequest('PATCH', '/v3/merchants/'.$merchantId.'/paymentMethodSettings/' . $paymentMethodId, $params);
    }


    /**
     * @throws IntegrationApiException
     */
    public function createAccountHolder(array $params): array
    {
        return $this->makeBalancePlatformRequest('POST', '/bcl/v2/accountHolders', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getAccountHolder(string $id): array
    {
        return $this->makeBalancePlatformRequest('GET', '/bcl/v2/accountHolders/'.$id, []);
    }

    /**
     * @throws IntegrationApiException
     */
    public function updateAccountHolder(string $id, array $params): array
    {
        return $this->makeBalancePlatformRequest('PATCH', '/bcl/v2/accountHolders/'.$id, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getTransactions(CarbonImmutable $createdSince, CarbonImmutable $createdUntil, string $accountHolderId, int $limit = 10, string $cursor = null): array
    {
        return $this->makeBalancePlatformRequest('GET', '/btl/v4/transactions', [
            'createdUntil' => $createdUntil->toIso8601String(),
            'createdSince' => $createdSince->toIso8601String(),
            'accountHolderId' => $accountHolderId,
            'limit' => $limit,
            'cursor' => $cursor,
        ]);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getSweeps(string $balanceAccountId): array
    {
        return $this->makeBalancePlatformRequest('GET', '/bcl/v2/balanceAccounts/'.$balanceAccountId.'/sweeps', []);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createSweep(string $balanceAccountId, array $params): array
    {
        return $this->makeBalancePlatformRequest('POST', '/bcl/v2/balanceAccounts/'.$balanceAccountId.'/sweeps', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getTransferInstrument(string $id): array
    {
        return $this->makeKycRequest('GET', '/lem/v3/transferInstruments/'.$id, []);
    }

    /**
     * @throws IntegrationApiException
     */
    public function verifyBankAccount(array $params): array
    {
        $params['applicationInfo'] = $this->makeApplicationInfo();

        return $this->makeZeroValueAuthRequest('POST', '/v71/payments', $params);
    }

    /**
     * Gets the display name for a transfer instrument.
     */
    public function getBankAccountName(string $transferInstrumentId): string
    {
        if (!$transferInstrumentId) {
            return '';
        }

        try {
            $transferInstrument = $this->getTransferInstrument($transferInstrumentId);
        } catch (IntegrationApiException) {
            // ignore exceptions when retrieving transfer instrument
            return '';
        }

        $type = $transferInstrument['bankAccount']['accountIdentification']['type'];
        if ('iban' == $type) {
            $last4 = substr($transferInstrument['bankAccount']['accountIdentification']['iban'], -4);
        } else {
            $last4 = substr($transferInstrument['bankAccount']['accountIdentification']['accountNumber'], -4);
        }

        $bankName = $transferInstrument['bankAccount']['bankName'] ?? '';

        // Look up US bank name by routing number when bank name is not provided
        if ('usLocal' == $type && !$bankName) {
            $routingNumber = $transferInstrument['bankAccount']['accountIdentification']['routingNumber'];
            $routingNumber = $this->routingNumberLookup->lookup($routingNumber);

            $bankName = $routingNumber?->bank_name;
        }

        return $bankName ? $bankName.' *'.$last4 : '*'.$last4;
    }

    /**
     * @throws IntegrationApiException
     */
    public function makeTransfer(array $params): array
    {
        return $this->makeBalancePlatformRequest('POST', '/btl/v4/transfers', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getTransfer(string $id): array
    {
        return $this->makeBalancePlatformRequest('GET', '/btl/v4/transfers/'.$id);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createBalanceAccount(array $params): array
    {
        return $this->makeBalancePlatformRequest('POST', '/bcl/v2/balanceAccounts', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getBalanceAccount(string $accountId): array
    {
        return $this->makeBalancePlatformRequest('GET', '/bcl/v2/balanceAccounts/'.$accountId, []);
    }

    /**
     * @throws IntegrationApiException
     */
    public function updateBalanceAccount(string $accountId, array $params): array
    {
        return $this->makeBalancePlatformRequest('PATCH', '/bcl/v2/balanceAccounts/'.$accountId, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createSession(array $params): array
    {
        $params['applicationInfo'] = $this->makeApplicationInfo();

        return $this->makeCheckoutRequest('POST', '/v71/sessions', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getSessionResult(string $sessionId, string $sessionResult): array
    {
        return $this->makeCheckoutRequest('GET', '/v71/sessions/'.$sessionId, ['sessionResult' => $sessionResult]);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createPayment(array $params): array
    {
        $params['applicationInfo'] = $this->makeApplicationInfo();

        return $this->makeCheckoutRequest('POST', '/v71/payments', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function submitPaymentDetails(array $params): array
    {
        $params['applicationInfo'] = $this->makeApplicationInfo();

        return $this->makeCheckoutRequest('POST', '/v71/payments/details', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getCardDetails(array $params): array
    {
        return $this->makeCheckoutRequest('POST', '/v71/cardDetails', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getPaymentMethods(array $params): array
    {
        $params['applicationInfo'] = $this->makeApplicationInfo();

        return $this->makeCheckoutRequest('POST', '/v71/paymentMethods', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function refund(string $paymentPspReference, array $params): array
    {
        $params['applicationInfo'] = $this->makeApplicationInfo();

        return $this->makeCheckoutRequest('POST', '/v71/payments/'.$paymentPspReference.'/refunds', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function cancel(string $paymentPspReference, array $params): array
    {
        $params['applicationInfo'] = $this->makeApplicationInfo();

        return $this->makeCheckoutRequest('POST', '/v71/payments/'.$paymentPspReference.'/cancels', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function capture(string $paymentPspReference, array $params): array
    {
        $params['applicationInfo'] = $this->makeApplicationInfo();

        return $this->makeCheckoutRequest('POST', '/v71/payments/'.$paymentPspReference.'/captures', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function downloadReport(string $url, string $apiKey): TmpFile
    {
        $options = [
            'headers' => [
                'x-API-key' => $apiKey,
            ],
        ];
        try {
            $tmpFile = new TmpFile('');
            $response = $this->makeRequest('GET', $url, $options);
            file_put_contents($tmpFile->getFileName(), $response->getContent(false));

            return $tmpFile;
        } catch (ExceptionInterface $e) {
            throw new IntegrationApiException('Downloading report failed: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    public function downloadSettlementReport(string $url): TmpFile
    {
        return $this->downloadReport($url, $this->settlementReportApiKey);
    }

    public function downloadPlatformReport(string $url): TmpFile
    {
        return $this->downloadReport($url, $this->reportApiKey);
    }

    /**
     * @throws IntegrationApiException
     */
    public function acceptDispute(string $adyenMerchantAccount, string $disputePspReference): array
    {
        $parameters = [
            'disputePspReference' => $disputePspReference,
            'merchantAccountCode' => $adyenMerchantAccount,
        ];

        return $this->makeDisputeRequest('POST', '/ca/services/DisputeService/v30/acceptDispute', $parameters);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getDefenceCodes(string $adyenMerchantAccount, string $disputePspReference): array
    {
        $parameters = [
            'disputePspReference' => $disputePspReference,
            'merchantAccountCode' => $adyenMerchantAccount,
        ];

        return $this->makeDisputeRequest('POST', '/ca/services/DisputeService/v30/retrieveApplicableDefenseReasons', $parameters);
    }

    /**
     * @param DisputeDocument[] $defenceDocuments
     *
     * @throws IntegrationApiException
     */
    public function supplyDefenseDocuments(string $adyenMerchantAccount, string $disputePspReference, array $defenceDocuments): array
    {
        $parameters = [
            'defenseDocuments' => array_map(fn ($doc) => $doc->toArray(), $defenceDocuments),
            'disputePspReference' => $disputePspReference,
            'merchantAccountCode' => $adyenMerchantAccount,
        ];

        return $this->makeDisputeRequest('POST', '/ca/services/DisputeService/v30/supplyDefenseDocument', $parameters);
    }

    /**
     * @throws IntegrationApiException
     */
    public function deleteDefenceDocument(string $adyenMerchantAccount, string $defenseDocumentType, string $disputePspReference): array
    {
        $parameters = [
            'defenseDocumentType' => $defenseDocumentType,
            'disputePspReference' => $disputePspReference,
            'merchantAccountCode' => $adyenMerchantAccount,
        ];

        return $this->makeDisputeRequest('POST', '/ca/services/DisputeService/v30/deleteDisputeDefenseDocument', $parameters);
    }

    /**
     * @throws IntegrationApiException
     */
    public function defendDispute(string $adyenMerchantAccount, string $disputePspReference, string $reasonCode): array
    {
        $parameters = [
            'defenseReasonCode' => $reasonCode,
            'disputePspReference' => $disputePspReference,
            'merchantAccountCode' => $adyenMerchantAccount,
        ];

        return $this->makeDisputeRequest('POST', '/ca/services/DisputeService/v30/defendDispute', $parameters);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getRecurringDetails(string $adyenMerchantAccount, string $shopperReference): array
    {
        $params = [
            'merchantAccount' => $adyenMerchantAccount,
            'shopperReference' => $shopperReference,
        ];

        return $this->makePalRequest('POST', '/pal/servlet/Recurring/v68/listRecurringDetails', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function disableRecurringDetails(string $adyenMerchantAccount, string $shopperReference, string $recurringDetailReference): array
    {
        $params = [
            'merchantAccount' => $adyenMerchantAccount,
            'shopperReference' => $shopperReference,
            'recurringDetailReference' => $recurringDetailReference,
        ];

        return $this->makePalRequest('POST', '/pal/servlet/Recurring/v68/disable', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getSplitConfiguration(string $merchantAccount, string $id): array
    {
        return $this->makeManagementRequest('GET', '/v3/merchants/'.$merchantAccount.'/splitConfigurations/'.$id, []);
    }

    /**
     * @throws IntegrationApiException
     */
    public function createSessionToken(array $params): array
    {
        return $this->makeAuthenticationRequest('POST', '/authe/api/v1/sessions', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function makeDisputeRequest(string $method, string $endpoint, ?array $params = null): array
    {
        $url = AdyenConfiguration::getUrl($this->adyenLiveMode, AdyenConfiguration::DISPUTE);

        return $this->makeApiRequest($method, $url.$endpoint, $this->apiKey, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function makeKycRequest(string $method, string $endpoint, ?array $params = null): array
    {
        $url = AdyenConfiguration::getUrl($this->adyenLiveMode, AdyenConfiguration::KYC);

        return $this->makeApiRequest($method, $url.$endpoint, $this->lemApiKey, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function makeManagementRequest(string $method, string $endpoint, ?array $params = null): array
    {
        $url = AdyenConfiguration::getUrl($this->adyenLiveMode, AdyenConfiguration::MANAGEMENT);

        return $this->makeApiRequest($method, $url.$endpoint, $this->apiKey, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function makeBalancePlatformRequest(string $method, string $endpoint, ?array $params = null): array
    {
        $url = AdyenConfiguration::getUrl($this->adyenLiveMode, AdyenConfiguration::BALANCE_PLATFORM);

        return $this->makeApiRequest($method, $url.$endpoint, $this->bclApiKey, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function makeCheckoutRequest(string $method, string $endpoint, ?array $params = null): array
    {
        $url = AdyenConfiguration::getUrl($this->adyenLiveMode, AdyenConfiguration::CHECKOUT);

        return $this->makeApiRequest($method, $url.$endpoint, $this->apiKey, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function makeZeroValueAuthRequest(string $method, string $endpoint, ?array $params = null): array
    {
        $url = AdyenConfiguration::getUrl($this->adyenLiveMode, AdyenConfiguration::CHECKOUT);

        return $this->makeApiRequest($method, $url.$endpoint, $this->apiKey, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function makePalRequest(string $method, string $endpoint, ?array $params = null): array
    {
        $url = AdyenConfiguration::getUrl($this->adyenLiveMode, AdyenConfiguration::PAL);

        return $this->makeApiRequest($method, $url.$endpoint, $this->apiKey, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function makeAuthenticationRequest(string $method, string $endpoint, ?array $params = null): array
    {
        $url = AdyenConfiguration::getUrl($this->adyenLiveMode, AdyenConfiguration::AUTHENTICATION);

        return $this->makeApiRequest($method, $url.$endpoint, $this->bclApiKey, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function makeApiRequest(string $method, string $url, string $apiKey, ?array $params = null): array
    {
        $options = [
            'headers' => [
                'x-API-key' => $apiKey,
                'Content-Type' => 'application/json',
            ],
        ];

        if ($params && 'GET' == $method) {
            $options['query'] = $params;
        } elseif ($params) {
            $options['json'] = $params;
        }

        try {
            $response = $this->makeRequest($method, $url, $options);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $message = $e->getMessage();
            $response = null;
            if ($e instanceof HttpExceptionInterface) {
                $response = $e->getResponse();
                $result = json_decode($response->getContent(false));
                if (is_object($result)) {
                    $messages = [];
                    if (isset($result->message)) {
                        $messages[] = $result->message;
                    }

                    if (isset($result->invalidFields)) {
                        foreach ($result->invalidFields as $invalidField) {
                            $messages[] = $invalidField->message;
                        }
                    }

                    if ($messages) {
                        $message = implode(', ', $messages);
                    }
                }
            }

            $e = new IntegrationApiException($message, $e->getCode(), $e);
            $e->setResponse($response);

            throw $e;
        }
    }

    /**
     * @throws ExceptionInterface
     */
    private function makeRequest(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->gatewayLogger->logSymfonyHttpRequest($method, $url, $options, self::MASKED_REQUEST_PARAMETERS);

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $this->gatewayLogger->logSymfonyHttpResponse($response);

            return $response;
        } catch (ExceptionInterface $e) {
            // log the response before rethrowing
            if ($e instanceof HttpExceptionInterface) {
                $response = $e->getResponse();
                $this->gatewayLogger->logSymfonyHttpResponse($response);
            }

            throw $e;
        }
    }

    private function makeApplicationInfo(): array
    {
        return [
            'externalPlatform' => [
                'name' => 'Flywire',
                'version' => '1.0',
                'integrator' => 'Flywire B2B',
            ],
            'merchantApplication' => [
                'name' => 'Invoiced',
                'version' => '1.0',
            ],
        ];
    }
}
