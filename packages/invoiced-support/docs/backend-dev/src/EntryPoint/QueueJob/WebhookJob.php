<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Webhooks\Models\WebhookAttempt;
use App\Webhooks\Pusher;

class WebhookJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, StatsdAwareInterface, MaxConcurrencyInterface
{
    use StatsdAwareTrait;

    public function __construct(private Pusher $pusher)
    {
    }

    public function perform(): void
    {
        $attempt = WebhookAttempt::queryWithoutMultitenancyUnsafe()
            ->where('id', $this->args['id'])
            ->oneOrNull();

        if (!$attempt instanceof WebhookAttempt) {
            return;
        }

        $this->pusher->performAttempt($attempt);

        $start = $this->args['queued_at'];
        $time = round((microtime(true) - $start) * 1000);
        $this->statsd->timing('webhook.delivery_time', $time);
    }

    public static function getMaxConcurrency(array $args): int
    {
        // Only 5 webhooks per account can be emitted at a time.
        return 5;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'webhook:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 60; // 1 minute
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
