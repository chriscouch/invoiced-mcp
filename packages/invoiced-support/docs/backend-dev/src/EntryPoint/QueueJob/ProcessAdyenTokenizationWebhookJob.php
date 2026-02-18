<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Integrations\Adyen\ValueObjects\AdyenTokenizationWebhookEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProcessAdyenTokenizationWebhookJob extends AbstractResqueJob implements MaxConcurrencyInterface, TenantAwareQueueJobInterface
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function perform(): void
    {
        // messy hack to convert an object to an array
        $data = json_decode((string) json_encode($this->args['event']), true);
        $this->dispatcher->dispatch(new AdyenTokenizationWebhookEvent($data));
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 10;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'adyen_tokenization_webhook:'.$args['tenant_id'];
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
