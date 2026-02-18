<?php

namespace App\Integrations\Flywire;

use App\Integrations\Exceptions\IntegrationApiException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DocVerifyClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private string $flywireDocVerifyUrl,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param resource $file
     *
     * @throws IntegrationApiException
     */
    public function extract($file): array
    {
        $params = [
            'file' => $file,
            'document' => json_encode(['mode' => 'file']),
        ];
        $result = $this->makeApiRequest('POST', '/extract?document-type=remittance_advice', $params);

        if ('success' != $result['status']) {
            throw new IntegrationApiException($result['error']);
        }

        return $result['data'];
    }

    /**
     * @throws IntegrationApiException
     */
    private function makeApiRequest(string $method, string $endpoint, ?array $params = null): array
    {
        $options = [];

        if ($params && 'GET' == $method) {
            $options['query'] = $params;
        } elseif ($params) {
            $options['body'] = $params;
        }

        try {
            $response = $this->makeRequest($method, $endpoint, $options);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new IntegrationApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws ExceptionInterface
     */
    private function makeRequest(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        try {
            return $this->httpClient->request($method, $this->flywireDocVerifyUrl.$endpoint, $options);
        } catch (ExceptionInterface $e) {
            $this->logger->error('Flywire doc-verify API call failed', [
                'exception' => $e,
                'response' => $e instanceof HttpExceptionInterface ? $e->getResponse()->toArray(false) : null,
            ]);

            throw $e;
        }
    }
}
