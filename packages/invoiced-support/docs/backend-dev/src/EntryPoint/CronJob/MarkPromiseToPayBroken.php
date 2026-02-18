<?php

namespace App\EntryPoint\CronJob;

use App\AccountsReceivable\Models\Invoice;
use App\Chasing\Models\PromiseToPay;
use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;

/**
 * Marks any invoices that have become past due.
 */
class MarkPromiseToPayBroken extends AbstractTaskQueueCronJob
{
    public function __construct(private readonly TenantContext $tenant)
    {
    }

    public function getTasks(): iterable
    {
        return Company::join(PromiseToPay::class, 'id', 'ExpectedPaymentDates.tenant_id')
            ->join(Invoice::class, 'ExpectedPaymentDates.invoice_id', 'id')
            ->where('ExpectedPaymentDates.broken = 0')
            ->where('ExpectedPaymentDates.date', time(), '<')
            ->where('Invoices.paid = 0')
            ->where('Invoices.voided = 0')
            ->where('Invoices.draft = 0')
            ->where('Companies.canceled = 0')
            ->all();
    }

    /**
     * @param Company $task
     */
    public function runTask(mixed $task): bool
    {
        $this->tenant->set($task);

        /** @var PromiseToPay[] $promises */
        $promises = PromiseToPay::join(Invoice::class, 'ExpectedPaymentDates.invoice_id', 'id')
            ->where('ExpectedPaymentDates.broken = 0')
            ->where('ExpectedPaymentDates.date', time(), '<')
            ->where('Invoices.paid = 0')
            ->where('Invoices.voided = 0')
            ->where('Invoices.draft = 0')
            ->first(1000);

        foreach ($promises as $promise) {
            $promise->broken = true;
            if (!$promise->save()) {
                return false;
            }
        }
        $this->tenant->clear();

        return true;
    }

    public static function getLockTtl(): int
    {
        return 59;
    }
}
