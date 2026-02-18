<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Flywire\Webhooks\FlywireRefundWebhook;

class ProcessFlywireRefundWebhookJob extends AbstractWebhookJob implements TenantAwareQueueJobInterface
{
    public function __construct(private readonly FlywireRefundWebhook $webhook, TenantContext $tenant)
    {
        parent::__construct($tenant);
    }

    public function getWebhookHandler(): FlywireRefundWebhook
    {
        return $this->webhook;
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'flywire_webhook:'.($args['event']['data']['refund_id'] ?? '');
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
