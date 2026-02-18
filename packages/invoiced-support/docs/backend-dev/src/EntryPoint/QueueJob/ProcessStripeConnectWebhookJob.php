<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Stripe\StripeConnectWebhook;

class ProcessStripeConnectWebhookJob extends AbstractWebhookJob
{
    public function __construct(private StripeConnectWebhook $webhook, TenantContext $tenant)
    {
        parent::__construct($tenant);
    }

    public function getWebhookHandler(): StripeConnectWebhook
    {
        return $this->webhook;
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'stripe_webhook:'.($args['event']['account'] ?? '');
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
