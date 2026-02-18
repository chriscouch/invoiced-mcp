<?php

namespace App\Integrations\Slack;

use App\Core\Multitenant\TenantContext;
use App\Core\Utils\DebugContext;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;

class SlackClient
{
    private const string POST = 'post';
    private const string GET = 'get';

    use IntegrationLogAwareTrait;

    public function __construct(
        private readonly string $endpoint,
        private readonly CloudWatchLogsClient $cloudWatchLogsClient,
        private readonly DebugContext $debugContext,
        private readonly TenantContext $tenant
    ) {
    }

    private function getClient(SlackAccount $slackAccount): Client
    {
        return new Client([
            'headers' => [
                'User-Agent' => 'Invoiced/1.0',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$slackAccount->access_token,
            ],
            'handler' => $this->makeGuzzleLogger('slack', $this->tenant->get(), $this->cloudWatchLogsClient, $this->debugContext),
        ]);
    }

    /**
     * @param self::POST|self::GET $method
     *
     * @throws IntegrationException
     */
    private function send(string $method, string $endpoint, array $data = [], bool $retried = false): object
    {
        $slackAccount = SlackAccount::queryWithCurrentTenant()->oneOrNull();
        if (!$slackAccount) {
            throw new IntegrationException('No Slack account found');
        }
        $client = $this->getClient($slackAccount);
        try {
            $endpoint = $this->endpoint.$endpoint;
            if (self::GET === $method) {
                $response = $client->get($endpoint);
            } else {
                $response = $client->post($endpoint, $data);
            }
        } catch (ClientException) {
            // when there is a 4xx response then we can
            // assume the integration is disabled or broken
            // and as such should disable slack notifications
            $slackAccount->delete();
            throw new IntegrationException('Slack integration disabled or broken');
        } catch (ServerException|ConnectException) {
            // if there is a server exception (5xx) then we
            // or a connection exception we
            // are going to retry once, and then drop it
            if ($retried) {
                throw new IntegrationException('Exception occurred, try again later');
            }

            return $this->send($method, $endpoint, $data, true);
        }
        $body = $response->getBody();
        $body->rewind();
        $data = $body->getContents();
        if (!$data) {
            throw new IntegrationException('Invalid response from Slack');
        }
        $json = json_decode($data);
        if (!$json) {
            throw new IntegrationException('Invalid response from Slack');
        }

        if (!$json->ok) {
            throw new IntegrationException($json->error);
        }

        return $json;
    }

    /**
     * @throws IntegrationException
     */
    public function listConversations(): array
    {
        $json = $this->send(self::GET, 'conversations.list');

        usort($json->channels, fn ($a, $b) => $a->name <=> $b->name);

        return array_map(fn ($channel) => [
            'id' => $channel->id,
            'name' => $channel->name,
        ], $json->channels);
    }

    /**
     * @throws IntegrationException
     */
    public function joinConversation(string $channelId): void
    {
        $this->send(self::POST, 'conversations.join', [
            'form_params' => [
                'channel' => $channelId,
            ],
        ]);
    }

    /**
     * @throws IntegrationException
     */
    public function postMessage(array $formParams): void
    {
        $this->send(self::POST, 'chat.postMessage', [
            'form_params' => $formParams,
        ]);
    }
}
