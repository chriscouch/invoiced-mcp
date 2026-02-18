<?php

namespace App\Integrations\Adyen\Libs;

use App\EntryPoint\QueueJob\ProcessAdyenTokenizationWebhookJob;
use App\PaymentProcessing\Models\PaymentFlow;

class AdyenTokenizationWebhook extends AdyenEmptyWebhook
{
    public function process(array $item): void
    {
        /** @var ?PaymentFlow $flow */
        $flow = PaymentFlow::queryWithoutMultitenancyUnsafe()
            ->where('identifier', $item['merchantReference'])
            ->oneOrNull();

        if (!$flow) {
            $this->statsd->increment('adyen.webhook.payments.not_found_tenant.payment');

            return;
        }

        $this->queue->enqueue(ProcessAdyenTokenizationWebhookJob::class, [
            'event' => $item,
            'tenant_id' => $flow->tenant()->id,
        ]);
    }
}
