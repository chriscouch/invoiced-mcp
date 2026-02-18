<?php

namespace App\Integrations\Adyen\Libs;

use App\EntryPoint\QueueJob\ProcessAdyenPaymentAuthorizationWebhookJob;
use App\PaymentProcessing\Models\PaymentFlow;
use Carbon\Carbon;

class AdyenPaymentWebhook extends AdyenEmptyWebhook
{
    public function process(array $item, int $delay = 0): void
    {
        /** @var ?PaymentFlow $flow */
        $flow = PaymentFlow::queryWithoutMultitenancyUnsafe()
            ->where('identifier', $item['merchantReference'])
            ->oneOrNull();

        if (!$flow) {
            $this->statsd->increment('adyen.webhook.payments.not_found_tenant.payment');

            return;
        }

        $this->queue->enqueueAt(
            Carbon::now()->addSeconds($delay),
            ProcessAdyenPaymentAuthorizationWebhookJob::class,
            [
                'event' => $item,
                'tenant_id' => $flow->tenant()->id,
            ]
        );
    }
}
