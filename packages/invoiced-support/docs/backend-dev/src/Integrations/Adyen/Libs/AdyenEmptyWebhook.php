<?php

namespace App\Integrations\Adyen\Libs;

use App\Core\Queue\Queue;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Integrations\Adyen\Interfaces\AdyenWebhookInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class AdyenEmptyWebhook implements StatsdAwareInterface, LoggerAwareInterface, AdyenWebhookInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    public function __construct(
        protected readonly Queue $queue
    ) {
    }

    public function process(array $item): void
    {
    }
}
