<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\TenantContext;
use App\Integrations\PayPal\Libs\PayPalWebhook;

class ProcessIpnJob extends AbstractWebhookJob
{
    public function __construct(TenantContext $tenant, private PayPalWebhook $handler)
    {
        parent::__construct($tenant);
    }

    public function getWebhookHandler(): PayPalWebhook
    {
        return $this->handler;
    }

    public static function getMaxConcurrency(array $args): int
    {
        // Only 5 paypal webhooks can be processed at a time.
        return 5;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'paypal_ipn';
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
