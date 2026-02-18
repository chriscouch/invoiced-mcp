<?php

namespace App\Integrations\Xero\Libs;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\OAuthConnectionManager;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use App\Integrations\Xero\Models\XeroAccount;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;

class XeroApi implements StatsdAwareInterface
{
    use IntegrationLogAwareTrait;
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    const RATE_LIMIT_HEADER = 'x-rate-limit-problem';
    const RATE_LIMIT_MINUTE = 'minute';

    const OAUTH_TOKEN_REJECTED = 'token_rejected';
    const OAUTH_USER_DISCONNECTED = 'the access token has not been authorized, or has been revoked by the user';
    private XeroAccount $account;
    private Client $httpClient;
    private bool $retriedRequest = false;

    public function __construct(
        private OAuthConnectionManager $oauthManager,
        private XeroOAuth $oauth,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
        private string $projectDir
    ) {
    }

    public function setAccount(XeroAccount $account): void
    {
        if (isset($this->account) && $this->account->id() == $account->id()) {
            return;
        }

        $this->account = $account;

        // clear local state
        unset($this->httpClient);
    }

    /**
     * Returns the Xero account used by this API client.
     */
    public function getAccount(): XeroAccount
    {
        return $this->account;
    }

    /**
     * Builds an HTTP client.
     */
    public function getHttpClient(): Client
    {
        if (isset($this->httpClient)) {
            return $this->httpClient;
        }

        $retryDecider = function (
            $retries,
            Request $request,
            ?Response $response = null,
            ?Throwable $exception = null
        ) {
            // Limit the number of retries to 3
            if ($retries >= 3) {
                return false;
            }

            // Retry connection exceptions
            if ($exception instanceof ConnectException) {
                return true;
            }

            return false;
        };

        $retryDelay = fn ($numberOfRetries) => 30000 * $numberOfRetries;

        $handlerStack = HandlerStack::create(new CurlHandler());
        $handlerStack->push(Middleware::retry($retryDecider, $retryDelay));

        $logFormatter = new MessageFormatter(MessageFormatter::DEBUG);
        $handlerStack->push(Middleware::log($this->getLogger(), $logFormatter));

        $this->httpClient = new Client([
            'handler' => $handlerStack,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        return $this->httpClient;
    }

    public function getLogger(): LoggerInterface
    {
        if (!isset($this->logger)) {
            $this->logger = $this->makeIntegrationLogger('xero', $this->account->tenant(), $this->cloudWatchLogsClient, $this->debugContext);
        }

        return $this->logger;
    }

    //
    // API Endpoints
    //

    /**
     * Gets a list of organizations connected to the Xero account.
     *
     * @throws OAuthException
     *
     * @return array An array of connection objects, each containing the following parameters: name, id
     */
    public function getConnections(): array
    {
        $httpClient = $this->getHttpClient();

        $headers = [
            'Authorization' => 'Bearer '.$this->account->access_token,
            'Content-Type' => 'application/json',
        ];

        try {
            $response = $httpClient->get('https://api.xero.com/connections', ['headers' => $headers]);
        } catch (BadResponseException $e) {
            throw new OAuthException($e->getMessage(), $e->getCode());
        }

        return json_decode($response->getBody());
    }

    /**
     * Retrieves an object for the connected organization.
     *
     * @throws IntegrationApiException
     */
    public function get(string $object, string $id): stdClass
    {
        $result = $this->jsonRequest('GET', "/$object/$id");

        return $result->$object[0];
    }

    /**
     * Retrieves multiple objects for the connected organization.
     *
     * @throws IntegrationApiException
     */
    public function getMany(string $object, array $query = [], array $headers = []): array
    {
        $result = $this->jsonRequest('GET', "/$object", $query, [], $headers);

        return $result->$object;
    }

    /**
     * Retrieves an object for the connected organization as a PDF.
     *
     * @throws IntegrationApiException
     */
    public function getPdf(string $object, string $id): string
    {
        $response = $this->request('GET', "/$object/$id", [], [], ['Accept' => 'application/pdf']);

        return $response->getBody();
    }

    /**
     * Creates an object for the connected organization.
     *
     * @throws IntegrationApiException
     */
    public function create(string $object, array $parameters): stdClass
    {
        $result = $this->jsonRequest('PUT', "/$object", [], $parameters);

        return $result->$object[0];
    }

    /**
     * Creates or updates an object for the connected organization.
     *
     * @throws IntegrationApiException
     */
    public function createOrUpdate(string $object, array $parameters): stdClass
    {
        $result = $this->jsonRequest('POST', "/$object", [], $parameters);

        return $result->$object[0];
    }

    /**
     * Gets the connected organization's name.
     *
     * @throws IntegrationApiException
     */
    public function getOrganization(string $id): stdClass
    {
        $result = $this->jsonRequest('GET', '/Organisation', [], [], ['xero-tenant-id' => $id]);

        return $result->Organisations[0];
    }

    /**
     * Creates an object for the connected organization.
     *
     * @throws IntegrationApiException
     */
    public function createAllocation(string $creditNoteId, array $parameters): stdClass
    {
        $result = $this->jsonRequest('PUT', "/CreditNotes/$creditNoteId/Allocations", [], $parameters);

        return $result->Allocations[0];
    }

    //
    // Helpers
    //

    /**
     * Gets the Xero API endpoint.
     */
    private function getApiUrl(): string
    {
        return 'https://api.xero.com/api.xro/2.0';
    }

    /**
     * Performs an authenticated request to the Xero API.
     *
     * @throws IntegrationApiException when the call fails
     */
    private function jsonRequest(string $method, string $endpoint, array $query = [], array $body = [], array $headers = []): stdClass
    {
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $response = $this->request($method, $endpoint, $query, $body, $headers);

        return json_decode($response->getBody());
    }

    /**
     * Performs an authenticated request to the Xero API.
     *
     * @throws IntegrationApiException when the call fails
     */
    private function request(string $method, string $endpoint, array $query, array $body, array $headers): ResponseInterface
    {
        $httpClient = $this->getHttpClient();
        try {
            $this->oauthManager->refresh($this->oauth, $this->account);
        } catch (OAuthException $e) {
            throw new IntegrationApiException($e->getMessage(), $e->getCode(), $e);
        }

        $this->statsd->increment('xero.api_call');

        $url = $this->getApiUrl().$endpoint;

        $params = [
            'headers' => array_replace([
                'Authorization' => 'Bearer '.$this->account->access_token,
                'xero-tenant-id' => $this->account->organization_id,
            ], $headers),
            'query' => $query,
        ];
        if (count($body) > 0) {
            $params['json'] = $body;
        }

        try {
            $response = $httpClient->request($method, $url, $params);
        } catch (BadResponseException $e) {
            $this->handleFailure($e);

            // if the above method does not throw an api exception then
            // the request should be retried
            return $this->request($method, $endpoint, $query, $body, $headers);
        }

        $this->retriedRequest = false;

        return $response;
    }

    /**
     * Handles a Xero API call failure. This will attempt a retry if the
     * failure is a result of rate-limiting.
     *
     * @throws IntegrationApiException when the request has failed and cannot be retried
     */
    private function handleFailure(BadResponseException $e): void
    {
        /** @var ResponseInterface $response */
        $response = $e->getResponse();
        $body = $response->getBody();
        $statusCode = $response->getStatusCode();

        // Check if we were rate-limited.
        // Xero has a 60 call/minute rate limit
        // and a 5,000 call/day rate limit.
        $rateLimitHeader = $response->getHeaderLine(self::RATE_LIMIT_HEADER);
        if (!$this->retriedRequest && self::RATE_LIMIT_MINUTE == strtolower($rateLimitHeader)) {
            // If we ran into the minute limit then wait 61 seconds and retry.
            // Each request will be retried at most one time.
            sleep(61);
            $this->retriedRequest = true;

            return;
        }

        // Check if the user de-authorized the account
        if (401 === $statusCode) {
            // parse the body
            // example: oauth_problem=token_rejected&oauth_problem_advice=The access token has not been authorized, or has been revoked by the user
            parse_str($body, $result);

            // check if the token was revoked
            if (isset($result['oauth_problem']) && self::OAUTH_TOKEN_REJECTED == $result['oauth_problem'] && self::OAUTH_USER_DISCONNECTED == strtolower($result['oauth_problem_advice'])) { /* @phpstan-ignore-line */
                $this->account->delete();

                throw new IntegrationApiException('Xero access token has not been authorized or was revoked. Please reconnect your Xero account in Settings > Integrations.');
            }

            throw new IntegrationApiException('An unknown authorization error occurred when trying to communicate with Xero. Please reconnect your Xero account in Settings > Integrations.');
        } elseif (404 === $statusCode) {
            // Attempt to parse an error message, however, 404 responses seem to not provide a JSON error.
            $message = $this->parseResponseError($response);
            if ('An unknown error has occurred when parsing the response from Xero' == $message) {
                throw new IntegrationApiException('The requested resource could not be found on Xero');
            }

            throw new IntegrationApiException($message);
        } elseif (429 == $statusCode) {
            $rateLimitHeader = $response->getHeaderLine(self::RATE_LIMIT_HEADER);
            $retryAfterSeconds = (int) $response->getHeaderLine('retry-after');
            $retryAfter = CarbonImmutable::now()->addSeconds($retryAfterSeconds)->diffForHumans();

            throw new IntegrationApiException('The Xero API '.strtolower($rateLimitHeader).' rate limit has been triggered. Please retry your request again after '.$retryAfter.'.');
        }

        throw new IntegrationApiException($this->parseResponseError($response));
    }

    /**
     * Parses error detail from a Xero 400 response.
     */
    private function parseResponseError(ResponseInterface $response): string
    {
        $body = json_decode($response->getBody());

        $message = $body->Message ?? 'An unknown error has occurred when parsing the response from Xero';

        // Strip out the validation exception message because it does not add value to the message.
        if ('A validation exception occurred' == $message) {
            $message = '';
        }

        if (isset($body->Elements)) {
            foreach ($body->Elements as $element) {
                foreach ($element->ValidationErrors as $error) {
                    $message .= "\n".$error->Message;
                }
            }
        }

        return trim($message);
    }
}
