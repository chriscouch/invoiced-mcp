<?php

namespace App\Integrations\Adyen\Libs;

use App\Companies\Models\Company;
use App\EntryPoint\QueueJob\ProcessAdyenChargebackWebhookJob;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;

class AdyenChargebackWebhook extends AdyenEmptyWebhook
{
    public function process(array $item): void
    {
        $tenant = $this->getNotificationItemTenant($item);

        if (!$tenant) {
            $this->statsd->increment('adyen.webhook.payments.not_found_tenant.chargeback');

            return;
        }

        $this->queue->enqueue(ProcessAdyenChargebackWebhookJob::class, [
            'event' => $item,
            'tenant_id' => $tenant->id,
        ]);
    }

    private function getNotificationItemTenant(array $item): ?Company
    {
        $dispute = Dispute::queryWithoutMultitenancyUnsafe()
            ->where('gateway_id', $item['pspReference'])
            ->oneOrNull();

        if ($dispute) {
            return $dispute->tenant();
        }

        if (!isset($item['originalReference'])) {
            return null;
        }

        $charge = Charge::queryWithoutMultitenancyUnsafe()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', $item['originalReference'])
            ->oneOrNull();

        return $charge?->tenant();
    }
}
