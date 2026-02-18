<?php

namespace App\Integrations\Flywire\Webhooks;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Flywire\Operations\SaveFlywireRefund;
use App\Integrations\Interfaces\WebhookHandlerInterface;

class FlywireRefundWebhook implements WebhookHandlerInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SaveFlywireRefund $createFlywireRefund
    ) {
    }

    public function process(Company $company, array $event): void
    {
        $this->createFlywireRefund->sync($event['data']['refund_id'], $event['data']['recipient_id']);
    }

    public function getCompanies(array $event): array
    {
        return [$this->tenantContext->get()];
    }

    public function shouldProcess(array &$event): bool
    {
        return true;
    }
}
