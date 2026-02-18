<?php

namespace App\Integrations\BusinessCentral;

use App\Core\Utils\DebugContext;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\Models\OAuthAccount;
use App\Integrations\OAuth\OAuthConnectionManager;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use stdClass;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BusinessCentralApi
{
    use IntegrationLogAwareTrait;

    private const BASE_URL = 'https://api.businesscentral.dynamics.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private OAuthConnectionManager $oauthManager,
        private BusinessCentralOAuth $oauth,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
    ) {
    }

    /**
     * @throws IntegrationApiException
     */
    public function getEnvironments(OAuthAccount $account): array
    {
        $result = $this->requestJson($account, 'GET', '/environments/v1.1');

        return $result->value;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getCompanies(OAuthAccount $account, string $environment): array
    {
        $result = $this->requestJson($account, 'GET', '/v2.0/'.$environment.'/api/v2.0/companies');

        return $result->value;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getAccounts(OAuthAccount $account): array
    {
        $result = $this->requestWithCompany($account, 'GET', 'accounts');

        return $result->value;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getCustomers(OAuthAccount $account, array $params = []): array
    {
        $result = $this->requestWithCompany($account, 'GET', 'customers', $params);

        return $result->value;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getSalesInvoices(OAuthAccount $account, array $params = []): array
    {
        $result = $this->requestWithCompany($account, 'GET', 'salesInvoices', $params);

        return $result->value;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getPdf(OAuthAccount $account, string $documentType, string $invoiceId): string
    {
        $result = $this->requestWithCompany($account, 'GET', $documentType.'('.$invoiceId.')/pdfDocument');
        $url = $result->{'pdfDocumentContent@odata.mediaReadLink'};

        return $this->requestMedia($account, $url);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getSalesCreditMemos(OAuthAccount $account, array $params = []): array
    {
        $result = $this->requestWithCompany($account, 'GET', 'salesCreditMemos', $params);

        return $result->value;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getGeneralLedgerEntries(OAuthAccount $account, array $params = []): array
    {
        $result = $this->requestWithCompany($account, 'GET', 'generalLedgerEntries', $params);

        return $result->value;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getCustomerPaymentJournals(OAuthAccount $account, array $params = []): array
    {
        $result = $this->requestWithCompany($account, 'GET', 'customerPaymentJournals', $params);

        return $result->value;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getCustomerPayments(OAuthAccount $account, string $journalId, array $params = []): array
    {
        $result = $this->requestWithCompany($account, 'GET', 'customerPaymentJournals('.$journalId.')/customerPayments', $params);

        return $result->value;
    }

    /**
     * @throws IntegrationApiException
     */
    public function createCustomerPayment(OAuthAccount $account, array $params): stdClass
    {
        return $this->requestWithCompany($account, 'POST', 'customerPayments', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function deleteCustomerPayment(OAuthAccount $account, string $journalId, string $id): void
    {
        $this->requestWithCompany($account, 'DELETE', 'customerPaymentJournals('.$journalId.')/customerPayments('.$id.')');
    }

    /**
     * @throws IntegrationApiException
     */
    private function requestWithEnvironment(OAuthAccount $account, string $method, string $endpoint, array $params = []): stdClass
    {
        $endpoint = '/v2.0/'.$account->getMetadata('environment').'/api/v2.0/'.$endpoint;

        return $this->requestJson($account, $method, $endpoint, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function requestWithCompany(OAuthAccount $account, string $method, string $endpoint, array $params = []): stdClass
    {
        $endpoint = 'companies('.$account->getMetadata('company').')/'.$endpoint;

        return $this->requestWithEnvironment($account, $method, $endpoint, $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function requestJson(OAuthAccount $account, string $method, string $endpoint, array $params = []): stdClass
    {
        $response = $this->request($account, $method, $endpoint, $params);

        if (204 == $response->getStatusCode()) {
            return (object) [];
        }

        return json_decode($response->getContent());
    }

    /**
     * @throws IntegrationApiException
     */
    private function requestMedia(OAuthAccount $account, string $endpoint): string
    {
        $response = $this->request($account, 'GET', $endpoint);

        return $response->getContent();
    }

    /**
     * @throws IntegrationApiException
     */
    private function request(OAuthAccount $account, string $method, string $endpoint, array $params = []): ResponseInterface
    {
        // Refresh access token before making a request.
        try {
            $this->oauthManager->refresh($this->oauth, $account);
        } catch (OAuthException $e) {
            throw new IntegrationApiException($e->getMessage(), $e->getCode(), $e);
        }

        if (str_starts_with($endpoint, 'http')) {
            $url = $endpoint;
        } else {
            $url = self::BASE_URL.$endpoint;
        }

        $options = [
            'auth_bearer' => $account->getToken()->accessToken,
            'headers' => [
                'User-Agent' => 'Invoiced/1.0',
                'Content-Type' => 'application/json',
            ],
        ];

        if ('GET' == $method && $params) {
            $options['query'] = $params;
        } elseif ('GET' != $method && $params) {
            $options['json'] = $params;
        }

        try {
            $response = $this->getHttpClient($account)->request($method, $url, $options);
            $response->getHeaders(); // Trigger an exception if request failed

            return $response;
        } catch (ExceptionInterface $e) {
            // log the response
            if ($e instanceof HttpExceptionInterface) {
                $response = $e->getResponse();
                $result = json_decode($response->getContent(false));
                if (is_object($result)) {
                    throw new IntegrationApiException($this->getErrorMessage($result));
                }
            }

            throw new IntegrationApiException('An unknown error occurred.');
        }
    }

    private function getHttpClient(OAuthAccount $account): HttpClientInterface
    {
        if (!isset($this->loggingHttpClient)) {
            $this->loggingHttpClient = $this->makeSymfonyLogger('business_central', $account->tenant(), $this->cloudWatchLogsClient, $this->debugContext, $this->httpClient);
        }

        return $this->loggingHttpClient;
    }

    private function getErrorMessage(object $result): string
    {
        if (isset($result->error->message)) {
            return $result->error->message;
        }

        return 'An unknown error occurred.';
    }
}
