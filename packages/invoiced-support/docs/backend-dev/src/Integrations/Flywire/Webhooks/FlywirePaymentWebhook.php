<?php

namespace App\Integrations\Flywire\Webhooks;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Flywire\Operations\SaveFlywirePayment;
use App\Integrations\Interfaces\WebhookHandlerInterface;
use App\PaymentProcessing\Models\MerchantAccount;

class FlywirePaymentWebhook implements WebhookHandlerInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SaveFlywirePayment $saveFlywirePayment,
    ) {
    }

    public function process(Company $company, array $event): void
    {
        $merchantAccount = MerchantAccount::findOrFail($event['merchant_account_id']);
        $this->saveFlywirePayment->sync($event['data']['payment_id'], $merchantAccount);
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
