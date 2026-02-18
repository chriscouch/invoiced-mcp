<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Integrations\Adyen\ValueObjects\AdyenPaymentAuthorizationWebhookEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProcessAdyenPaymentAuthorizationWebhookJob extends AbstractResqueJob implements MaxConcurrencyInterface, TenantAwareQueueJobInterface
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function perform(): void
    {
        // messy hack to convert an object to an array
        $data = json_decode((string) json_encode($this->args['event']), true);
        $this->dispatcher->dispatch(new AdyenPaymentAuthorizationWebhookEvent($data));
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 10;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'adyen_payment_authorization_webhook:'.$args['tenant_id'];
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
