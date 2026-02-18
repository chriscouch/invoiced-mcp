<?php

namespace App\Webhooks;

use App\Core\Mailer\Mailer;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Webhooks\Interfaces\PayloadStorageInterface;
use App\Webhooks\Models\Webhook;
use App\Webhooks\Models\WebhookAttempt;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;

class Pusher implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    const CONNECT_TIMEOUT = 10; // seconds
    const WEBHOOK_TIMEOUT = 30; // seconds
    const MAX_RETRIES = 48;
    const RETRY_INTERVAL = 3600; // seconds

    private Client $client;

    public function __construct(
        private PayloadStorageInterface $payloadStorage,
        private Mailer $mailer,
        Client $client = null
    ) {
        $this->client = $client ?? new Client([
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
    }

    /**
     * Gets the HTTP client.
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Gets the headers for webhook calls.
     */
    public function getHeaders(string $payload, string $secret): array
    {
        return [
            'User-Agent' => 'Invoiced/1.0',
            'Content-Type' => 'application/json',
            'X-Invoiced-Signature' => $this->signRequest($payload, $secret),
        ];
    }

    /**
     * Signs a webhook request.
     */
    public function signRequest(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Calls a webhook endpoint with a payload.
     *
     * @param string $endpoint URL
     * @param string $payload  payload to be converted to JSON
     *
     * @throws \GuzzleHttp\Exception\TransferException
     */
    public function call(string $endpoint, string $payload, string $secret): ResponseInterface
    {
        $opts = [
            'headers' => $this->getHeaders($payload, $secret),
            'body' => $payload,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => self::WEBHOOK_TIMEOUT,
            // don't follow redirects, i.e. 301, 302
            'allow_redirects' => false,
            // don't throw an exception whenever
            // there is a 4xx or 5xx response
            // since we handle non-2xx already
            'http_errors' => false,
        ];

        return $this->client->request('POST', $endpoint, $opts);
    }

    /**
     * Performs a webhook attempt.
     */
    public function performAttempt(WebhookAttempt $attempt): bool
    {
        $webhook = Webhook::where('url', $attempt->url)->oneOrNull();

        // if the webhook no longer exists then de-schedule
        // any future attempts for this event / handler
        if (!$webhook) {
            $attempt->next_attempt = null;
            $attempt->save();

            return false;
        }

        $payload = $this->payloadStorage->retrieve($attempt);
        if (!$payload) {
            return false;
        }

        $responseCode = 0;

        try {
            $response = $this->call($attempt->url, $payload, $webhook->getSecret());

            // record attempt
            $attempts = $attempt->attempts;
            $attempts[] = $this->buildFromResponse($response);
            $attempt->attempts = $attempts;

            $responseCode = $response->getStatusCode();
        } catch (TransferException $e) {
            // handle exceptions
            $attempts = $attempt->attempts;
            $attempts[] = $this->buildFromError($e);
            $attempt->attempts = $attempts;
        }

        if ($attempt->succeeded()) {
            $attempt->next_attempt = null;
            $attempt->save();
            $this->statsd->increment('webhook.succeeded');

            return true;
        }

        $this->handleFailedAttempt($attempt, $responseCode);

        return false;
    }

    /**
     * Builds a webhook attempt summary from a PSR-7 response.
     */
    private function buildFromResponse(ResponseInterface $response): array
    {
        return [
            'status_code' => $response->getStatusCode(),
            'timestamp' => time(),
        ];
    }

    /**
     * Builds a webhook attempt summary from a Guzzle exception.
     */
    private function buildFromError(TransferException $e): array
    {
        return [
            'error' => $e->getMessage(),
            'timestamp' => time(),
        ];
    }

    /**
     * Handles a failed webhook attempt.
     */
    private function handleFailedAttempt(WebhookAttempt $attempt, int $statusCode): void
    {
        $this->statsd->increment('webhook.failed');

        // Disable webhooks for this URL in the following scenarios:
        // 1) Reached the max # of retries
        // 2) Returned 410 Gone
        $numAttempts = count($attempt->attempts);
        if ($numAttempts >= self::MAX_RETRIES || 410 == $statusCode) {
            $company = $attempt->tenant();
            /** @var Webhook[] $webhooks */
            $webhooks = Webhook::queryWithTenant($company)
                ->where('url', $attempt->url)
                ->where('enabled', true)
                ->first(100);

            foreach ($webhooks as $webhook) {
                $webhook->disable();

                // notify the user when a webhook is disabled
                // (do not send this when it's an internal webhook)
                if (!$webhook->protected) {
                    $this->mailer->sendToAdministrators(
                        $webhook->tenant(),
                        [
                            'subject' => 'Problem with your Invoiced webhook',
                        ],
                        'webhook-disabled', [
                        'company' => $company->name,
                            'url' => $webhook->url,
                        ]);
                }
            }

            $attempt->next_attempt = null;
            // otherwise schedule the next attempt
        } else {
            $attempt->next_attempt = time() + self::RETRY_INTERVAL;
        }

        $attempt->save();
    }
}
