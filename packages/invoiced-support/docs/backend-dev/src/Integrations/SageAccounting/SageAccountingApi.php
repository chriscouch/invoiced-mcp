<?php

namespace App\Integrations\SageAccounting;

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

class SageAccountingApi
{
    use IntegrationLogAwareTrait;

    private const BASE_URL = 'https://api.accounting.sage.com/v3.1';

    public function __construct(
        private HttpClientInterface $httpClient,
        private OAuthConnectionManager $oauthManager,
        private SageAccountingOAuth $oauth,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
    ) {
    }

    /**
     * @throws IntegrationApiException
     */
    public function getCustomers(OAuthAccount $account, array $params = []): stdClass
    {
        $params['attributes'] = 'all';
        $params['contact_type_id'] = 'CUSTOMER';

        return $this->requestJson($account, 'GET', '/contacts', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getInvoices(OAuthAccount $account, array $params = []): stdClass
    {
        $params['attributes'] = 'all';

        return $this->requestJson($account, 'GET', '/sales_invoices', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    public function getCreditNotes(OAuthAccount $account, array $params = []): stdClass
    {
        $params['attributes'] = 'all';

        return $this->requestJson($account, 'GET', '/sales_credit_notes', $params);
    }

    /**
     * @throws IntegrationApiException
     */
    private function requestJson(OAuthAccount $account, string $method, string $endpoint, array $params = []): stdClass
    {
        $response = $this->request($account, $method, $endpoint, $params);

        return json_decode($response->getContent());
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
            $this->loggingHttpClient = $this->makeSymfonyLogger('sage_accounting', $account->tenant(), $this->cloudWatchLogsClient, $this->debugContext, $this->httpClient);
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
