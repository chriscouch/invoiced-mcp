<?php

namespace App\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @property LoggerInterface $logger
 */
class CsAdminApiClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private HttpClientInterface $client;
    private string $url;
    private string $secret;

    public function __construct(string $csAdminApiUrl, string $csAdminApiSecret, HttpClientInterface $client)
    {
        $this->url = $csAdminApiUrl;
        $this->secret = $csAdminApiSecret;
        $this->client = $client;
    }

    public function request(string $endpoint, array $parameters): object
    {
        $body = (string) json_encode($parameters);
        $signature = hash_hmac('sha256', $body, $this->secret);

        $headers = [
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
        ];

        // This is required in dev for our container to communicate with the invoiced container
        if (str_contains($this->url, 'host.docker.internal')) {
            $headers['Host'] = 'invoiced.localhost';
        }

        try {
            $response = $this->client->request('POST', $this->url.$endpoint, [
                'body' => $body,
                'headers' => $headers,
            ]);

            return json_decode($response->getContent());
        } catch (ExceptionInterface $e) {
            $this->logger->error('API call failed', ['exception' => $e]);

            return (object) ['error' => 'An internal server error occurred'];
        }
    }
}
