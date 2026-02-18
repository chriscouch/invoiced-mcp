<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\TenantContext;
use App\Integrations\GoCardless\GoCardlessWebhook;

class ProcessGoCardlessWebhookJob extends AbstractWebhookJob
{
    public function __construct(TenantContext $tenant, private GoCardlessWebhook $handler)
    {
        parent::__construct($tenant);
    }

    public function getWebhookHandler(): GoCardlessWebhook
    {
        return $this->handler;
    }

    public static function getMaxConcurrency(array $args): int
    {
        // Only 1 webhook per connection can be processed at a time.
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'gocardless_webhook:'.($args['event']['links']['organisation'] ?? '');
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
