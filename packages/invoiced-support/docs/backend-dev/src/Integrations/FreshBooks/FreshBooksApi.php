<?php

namespace App\Integrations\FreshBooks;

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

class FreshBooksApi
{
    use IntegrationLogAwareTrait;

    private const BASE_URL = 'https://api.freshbooks.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private OAuthConnectionManager $oauthManager,
        private FreshBooksOAuth $oauth,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
    ) {
    }

    /**
     * @throws IntegrationApiException
     */
    public function getUserProfile(OAuthAccount $account): stdClass
    {
        $result = $this->requestJson($account, 'GET', '/auth/api/v1/users/me');

        return $result->response;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getClients(OAuthAccount $account, array $params = []): stdClass
    {
        $result = $this->requestWithAccount($account, 'GET', 'users/clients', $params);

        return $result->response->result;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getInvoices(OAuthAccount $account, array $params = []): stdClass
    {
        $result = $this->requestWithAccount($account, 'GET', 'invoices/invoices', $params);

        return $result->response->result;
    }

    /**
     * @throws IntegrationApiException
     */
    public function getCreditMemos(OAuthAccount $account, array $params = []): stdClass
    {
        $result = $this->requestWithAccount($account, 'GET', 'salesCreditMemos', $params);

        return $result->response->result;
    }

    /**
     * @throws IntegrationApiException
     */
    private function requestWithAccount(OAuthAccount $account, string $method, string $endpoint, array $params = []): stdClass
    {
        $endpoint = '/accounting/account/'.$account->getMetadata('account').'/'.$endpoint;

        return $this->requestJson($account, $method, $endpoint, $params);
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
            $this->loggingHttpClient = $this->makeSymfonyLogger('freshbooks', $account->tenant(), $this->cloudWatchLogsClient, $this->debugContext, $this->httpClient);
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
