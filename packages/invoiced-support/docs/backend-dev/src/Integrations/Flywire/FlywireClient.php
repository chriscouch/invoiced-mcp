<?php

namespace App\Integrations\Flywire;

use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\Traits\FlywireTrait;
use App\PaymentProcessing\Libs\GatewayLogger;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FlywireClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use FlywireTrait;

    private const int EXPIRATION_BUFFER = 600;

    protected const array MASKED_REQUEST_PARAMETERS = [
        'X-Authentication-Key',
        'token',
        'client_secret',
    ];

    protected CarbonImmutable $validTill;
    protected string $accessToken = '';
    protected string $flywireClientId;
    protected string $flywireClientSecret;
    protected string $flywirePrivateApiUrl;
    protected string $flywireCheckoutUrl;
    protected HttpClientInterface $httpClient;
    protected GatewayLogger $gatewayLogger;
    protected UrlGeneratorInterface $urlGenerator;

    public function __construct(
        string $flywireClientId,
        string $flywireClientSecret,
        string $flywirePrivateApiUrl,
        string $flywireCheckoutUrl,
        HttpClientInterface $httpClient,
        GatewayLogger $gatewayLogger,
        UrlGeneratorInterface $urlGenerator,
    ) {
        $this->flywireClientId = $flywireClientId;
        $this->flywireClientSecret = $flywireClientSecret;
        $this->flywirePrivateApiUrl = $flywirePrivateApiUrl;
        $this->flywireCheckoutUrl = $flywireCheckoutUrl;
        $this->httpClient = $httpClient;
        $this->gatewayLogger = $gatewayLogger;
        $this->urlGenerator = $urlGenerator;

        $this->validTill = CarbonImmutable::now();
    }

    protected function getAccessToken(): string
    {
        if ($this->validTill->greaterThan(CarbonImmutable::now()) && $this->accessToken) {
            return $this->accessToken;
        }

        try {
            $response = $this->makeRequest('POST', $this->flywirePrivateApiUrl.'/oauth/token', [
                'json' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->flywireClientId,
                    'client_secret' => $this->flywireClientSecret,
                ],
            ]);

            if ($response->getStatusCode() != 200) {
                $this->logger->error('Flywire API call failed, getting the access token for the approve client, response code: ' . $response->getStatusCode());
                return '';
            }

            $data = $response->toArray();

            $this->validTill = CarbonImmutable::now()->addSeconds($data['expires_in'] - self::EXPIRATION_BUFFER);

            return $this->accessToken = $data['access_token'];
        } catch (ExceptionInterface $e) {
            throw new IntegrationApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws IntegrationApiException
     */
    protected function makeApiRequest(string $method, string $endpoint, ?array $params = null): array
    {
        $key = $this->getAccessToken();

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => 'application/json',
            ],
        ];

        if ($params && 'GET' == $method) {
            $options['query'] = $params;
        } elseif ($params) {
            $options['json'] = $params;
        }

        try {
            $response = $this->makeRequest($method, $this->flywirePrivateApiUrl.$endpoint, $options);

            if (!in_array($response->getStatusCode(), [200, 201, 204])) {
                $this->logger->error('Flywire Approve API call failed, response payload: ' . json_encode($response->toArray()));
            }

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new IntegrationApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws ExceptionInterface
     */
    protected function makeRequest(string $method, string $url, array $options = []): ResponseInterface
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

    protected function getErrorMessage(ResponseInterface $response): ?string
    {
        $result = json_decode($response->getContent(false));
        if (!is_object($result)) {
            return null;
        }

        if (isset($result->errors)) {
            $messages = [];

            foreach ($result->errors as $error) {
                $error = property_exists($error, 'error') ? $error->error : '/';
                $errorDescription = property_exists($error, 'error_description') ? $error->error_description : '/';
                $message = property_exists($error, 'message') ? $error->message : '/';
                $type = property_exists($error, 'type') ? $error->type : '/';
                $param = property_exists($error, 'param') ? $error->param : '/';
                $source = property_exists($error, 'source') ? $error->source : '/';

                $messages[] = 'Error: ' . $error . ', description: ' . $errorDescription;
                $messages[] = 'Message: ' . $message . ' Type: ' . $type . ' Param: ' . $param . ' Source: ' . $source;
            }

            return implode(' ', $messages);
        }

        return $result->title ?? 'An unknown error has occurred';
    }
}
