<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhook;

class ProcessPlaidWebhookJob extends AbstractWebhookJob
{
    public function __construct(private PlaidTransactionWebhook $plaidWebhook, TenantContext $tenant)
    {
        parent::__construct($tenant);
    }

    public function getWebhookHandler(): PlaidTransactionWebhook
    {
        return $this->plaidWebhook;
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'plaid_webhook:'.($args['event']['item_id'] ?? '');
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
