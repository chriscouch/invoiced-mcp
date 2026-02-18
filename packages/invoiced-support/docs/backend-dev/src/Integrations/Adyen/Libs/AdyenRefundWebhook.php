<?php

namespace App\Integrations\Adyen\Libs;

use App\EntryPoint\QueueJob\ProcessAdyenRefundWebhookJob;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Refund;

class AdyenRefundWebhook extends AdyenEmptyWebhook
{
    public function process(array $item): void
    {
        $refund = Refund::queryWithoutMultitenancyUnsafe()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', $item['pspReference'])
            ->oneOrNull();

        if (!$refund) {
            $this->statsd->increment('adyen.webhook.payments.not_found_tenant.refund');

            return;
        }

        $this->queue->enqueue(ProcessAdyenRefundWebhookJob::class, [
            'event' => $item,
            'tenant_id' => $refund->tenant()->id,
        ]);
    }
}
