<?php

namespace App\Webhooks\EventSubscriber;

use App\Core\Queue\Queue;
use App\ActivityLog\Models\Event;
use App\Webhooks\Interfaces\PayloadStorageInterface;
use App\Webhooks\Models\Webhook;
use App\Webhooks\Models\WebhookAttempt;
use App\Webhooks\Pusher;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

/**
 * Emits any webhooks for the event.
 */
class WebhookSubscriber implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private Queue $queue, private PayloadStorageInterface $payloadStorage)
    {
    }

    public function onEventDispatch(Event $event): void
    {
        /** @var Webhook[] $webhooks */
        $webhooks = Webhook::queryWithTenant($event->tenant())
            ->where('enabled', true)
            ->all();

        foreach ($webhooks as $webhook) {
            try {
                $this->emit($webhook, $event);
            } catch (Throwable $e) {
                $this->logger->error('Unable to emit webhook', ['exception' => $e]);
            }
        }
    }

    /**
     * Queues an event to be sent out to this webhook.
     */
    public function emit(Webhook $webhook, Event $event): bool
    {
        // check if the event type supports webhooks
        if (!$this->isEventSupported($webhook, $event->type)) {
            return false;
        }

        // create a webhook attempt
        $attempt = new WebhookAttempt();
        $attempt->url = $webhook->url;
        $attempt->tenant_id = $webhook->tenant_id;
        $attempt->event_id = (int) $event->id();
        $attempt->next_attempt = time() + Pusher::RETRY_INTERVAL;
        $attempt->save();

        $this->payloadStorage->store($attempt, (string) json_encode($event->toArray()));

        // queue it
        $attempt->queue($this->queue);

        return true;
    }

    /**
     * Gets the event types supported by the webhook.
     */
    public function isEventSupported(Webhook $webhook, string $event): bool
    {
        if (in_array('*', $webhook->events)) {
            return true;
        }

        return in_array($event, $webhook->events);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'object_event.dispatch' => 'onEventDispatch',
        ];
    }
}
