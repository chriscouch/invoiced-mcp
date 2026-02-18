<?php

namespace App\Integrations\NetSuite\Libs;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\NetSuite\Interfaces\PathProviderInterface;
use App\Integrations\NetSuite\Models\NetSuiteAccount;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use NetSuite\Classes\GetDataCenterUrlsRequest;
use NetSuite\Classes\GetDataCenterUrlsResult;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Wrapper around the NetSuite client library.
 */
class NetSuiteApi implements StatsdAwareInterface
{
    use IntegrationLogAwareTrait;
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private const API_VERSION = '2018_2';
    private const CONNECT_TIMEOUT = 30;
    private const WEBSERVICES_HOST = 'https://webservices.netsuite.com';
    private const RESTLET_ENDPOINT = '/app/site/hosting/restlet.nl';

    public function __construct(
        private string $projectDir,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
        private string $netSuiteConsumerKey,
        private string $netSuiteConsumerSecret
    ) {
    }

    public function getLogger(NetSuiteAccount $account): LoggerInterface
    {
        if (!isset($this->logger)) {
            $this->logger = $this->makeIntegrationLogger('netsuite', $account->tenant(), $this->cloudWatchLogsClient, $this->debugContext);
        }

        return $this->logger;
    }

    /**
     * Looks up the data center URLs to use for this account from NetSuite.
     * As a best practice this should be used instead of calling the generic
     * web services or restlet API endpoints.
     *
     * @throws IntegrationApiException
     */
    public function getDataCenterUrls(NetSuiteAccount $account): GetDataCenterUrlsResult
    {
        $request = new GetDataCenterUrlsRequest();
        $request->account = $account->account_id;

        $client = $this->buildSoapClient($account);

        try {
            $result = $client->getDataCenterUrls($request);
        } catch (\Exception $e) {
            throw new IntegrationApiException('Could not retrieve NetSuite data center URLs: '.$e->getMessage(), 0, $e);
        }

        if (!$result->getDataCenterUrlsResult->status->isSuccess) {
            throw new IntegrationApiException('Could not retrieve NetSuite data center URLs');
        }

        return $result->getDataCenterUrlsResult;
    }

    /**
     * Invokes a restlet within NetSuite.
     *
     * @throws IntegrationApiException
     *
     * @return object|null - NS response
     */
    public function callRestlet(NetSuiteAccount $account, string $method, PathProviderInterface $record, array $data = []): ?object
    {
        $client = $this->buildRestletClient($account);

        $url = $account->restlet_domain.self::RESTLET_ENDPOINT;
        $deployId = $record->getDeploymentId();
        $scriptId = $record->getScriptId();

        $query = [
            'script' => $scriptId,
            'deploy' => $deployId,
            'realm' => $account->account_id,
        ];

        $parameters = [];
        if ('get' === $method) {
            $query = array_merge($query, $data);
        } else {
            $parameters['json'] = $data;
        }
        $parameters['query'] = $query;
        $parameters['headers'] = ['Authorization' => $this->authHeader($account, $url, $query, $method)];

        $this->statsd->increment('netsuite.restlet_call');

        try {
            $response = $client->request($method, $url, $parameters);
        } catch (GuzzleException $e) {
            if (403 === $e->getCode()) {
                throw new IntegrationApiException('NetSuite authentication error. Please verify the credentials you have supplied are correct', $e->getCode(), $e);
            }
            if ($e instanceof RequestException) {
                if ($response = $e->getResponse()) {
                    throw new IntegrationApiException($response->getBody(), $e->getCode(), $e);
                }
            }

            throw new IntegrationApiException($e->getMessage(), $e->getCode(), $e);
        }

        return json_decode($response->getBody());
    }

    /**
     * Builds an API client for making SOAP calls.
     */
    private function buildSoapClient(NetSuiteAccount $account): NetSuiteRetryClient
    {
        ini_set('default_socket_timeout', '600');

        $config = [
            'endpoint' => self::API_VERSION,
            'host' => self::WEBSERVICES_HOST,
            'account' => $account->account_id,
            'consumerKey' => getenv('NETSUITE_CONSUMER_KEY'),
            'consumerSecret' => getenv('NETSUITE_CONSUMER_SECRET'),
            'token' => $account->token,
            'tokenSecret' => $account->token_secret,
            'signatureAlgorithm' => 'sha256',
        ];

        $soapOptions = [
            'connection_timeout' => self::CONNECT_TIMEOUT,
        ];

        $client = new NetSuiteRetryClient($config, $this->projectDir, $soapOptions);
        $client->setLogger($this->getLogger($account));

        return $client;
    }

    /**
     * Builds an API client for calling Restlets.
     */
    private function buildRestletClient(NetSuiteAccount $account): Client
    {
        return new Client([
            'handler' => $this->makeGuzzleLogger('netsuite', $account->tenant(), $this->cloudWatchLogsClient, $this->debugContext),
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
    }

    /**
     * make authentication header.
     */
    private function authHeader(NetSuiteAccount $account, string $url, array $query, string $type): string
    {
        $oauth_nonce = md5((string) mt_rand());
        $oauth_timestamp = time();
        $oauth_signature_method = 'HMAC-SHA256';
        $oauth_version = '1.0';

        $query = array_merge($query, [
            'oauth_consumer_key' => $this->netSuiteConsumerKey,
            'oauth_nonce' => $oauth_nonce,
            'oauth_signature_method' => $oauth_signature_method,
            'oauth_timestamp' => $oauth_timestamp,
            'oauth_token' => $account->token,
            'oauth_version' => $oauth_version,
        ]);

        ksort($query);

        $base_string = strtoupper($type).'&'.urlencode($url).'&'.urlencode(http_build_query($query));

        $sig_string = urlencode($this->netSuiteConsumerSecret).'&'.urlencode($account->token_secret);
        $signature = base64_encode(hash_hmac('sha256', $base_string, $sig_string, true));

        return 'OAuth '
            .'oauth_signature="'.rawurlencode($signature).'", '
            .'oauth_version="'.rawurlencode($oauth_version).'", '
            .'oauth_nonce="'.rawurlencode($oauth_nonce).'", '
            .'oauth_signature_method="'.rawurlencode($oauth_signature_method).'", '
            .'oauth_consumer_key="'.rawurlencode($this->netSuiteConsumerKey).'", '
            .'oauth_token="'.rawurlencode($account->token).'", '
            .'oauth_timestamp="'.rawurlencode((string) $oauth_timestamp).'", '
            .'realm="'.rawurlencode($account->account_id).'"';
    }
}
