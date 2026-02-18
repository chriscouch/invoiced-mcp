<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Flywire\Webhooks\FlywirePaymentWebhook;

class ProcessFlywireWebhookJob extends AbstractWebhookJob implements TenantAwareQueueJobInterface
{
    public function __construct(private readonly FlywirePaymentWebhook $webhook, TenantContext $tenant)
    {
        parent::__construct($tenant);
    }

    public function getWebhookHandler(): FlywirePaymentWebhook
    {
        return $this->webhook;
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'flywire_webhook:'.($args['event']['data']['payment_id'] ?? '');
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
