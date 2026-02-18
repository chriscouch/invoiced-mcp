<?php

namespace App\Notifications\Emitters;

use App\Companies\Models\Company;
use App\Core\Authentication\Models\User;
use App\Core\Utils\DebugContext;
use App\ActivityLog\Models\Event;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\Slack;
use App\Integrations\Slack\SlackAccount;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use App\Notifications\Interfaces\EmitterInterface;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;

/**
 * @deprecated for automations
 */
class SlackEmitter implements EmitterInterface
{
    use IntegrationLogAwareTrait;

    private Client $client;
    private bool $retried = false;

    public function __construct(
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
        private IntegrationFactory $integrationFactory,
    ) {
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    public function emit(Event $event, User $user = null): bool
    {
        /** @var Slack $slack */
        $slack = $this->integrationFactory->get(IntegrationType::Slack, $event->tenant());
        if (!$slack->isConnected()) {
            return false;
        }

        /** @var SlackAccount $slackAccount */
        $slackAccount = $slack->getAccount();
        $message = $this->buildMessage($event);
        $this->postToSlack($message, $slackAccount);

        return true;
    }

    /**
     * Builds a message for a Slack channel.
     */
    public function buildMessage(Event $event): array
    {
        $eventName = $event->getTitle();
        $text = strip_tags($event->getMessage()->toString(false));

        return [
            'attachments' => [
                [
                    'title' => "<{$event->href}|$eventName>",
                    'text' => $text,
                    'color' => $this->getColor($event->type),
                ],
            ],
        ];
    }

    /**
     * Gets the HTML color code highlighting for a given event type.
     */
    public function getColor(string $eventType): ?string
    {
        // deleted events
        if (str_contains($eventType, '.deleted')) {
            return '#C14543'; // red
        }

        // paid events
        if (str_contains($eventType, '.paid')) {
            return '#54BF83'; // green
        }

        // viewed events
        if (str_contains($eventType, '.viewed')) {
            return '#4B94D9'; // blue
        }

        return null;
    }

    /**
     * Posts a message to Slack.
     */
    private function postToSlack(array $message, SlackAccount $slackAccount): void
    {
        $webhookUrl = $slackAccount->webhook_url;
        if (!$webhookUrl) {
            return;
        }

        $client = $this->getClient($slackAccount->tenant());

        try {
            $client->post($webhookUrl, [
                'json' => $message,
            ]);
        } catch (ClientException) {
            // when there is a 4xx response then we can
            // assume the integration is disabled or broken
            // and as such should disable slack notifications
            $slackAccount->delete();
        } catch (ServerException) {
            // if there is a server exception (5xx) then we
            // are going to retry once, and then drop it
            if (!$this->retried) {
                $this->retried = true;
                $this->postToSlack($message, $slackAccount);
            }
        } catch (ConnectException) {
            // if there is a connection exception we
            // are going to retry once, and then drop it
            if (!$this->retried) {
                $this->retried = true;
                $this->postToSlack($message, $slackAccount);
            }
        }
    }

    /**
     * Gets a Slack client.
     */
    private function getClient(Company $company): Client
    {
        if (!isset($this->client)) {
            $this->client = new Client([
                'headers' => [
                    'User-Agent' => 'Invoiced/1.0',
                    'Content-Type' => 'application/json',
                ],
                'handler' => $this->makeGuzzleLogger('slack', $company, $this->cloudWatchLogsClient, $this->debugContext),
            ]);
        }

        return $this->client;
    }
}
