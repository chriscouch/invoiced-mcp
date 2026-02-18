<?php

namespace App\EntryPoint\CronJob;

use App\Chasing\Legacy\InvoiceChaser;
use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use Doctrine\DBAL\Connection;

/**
 * Chases any active invoices that need chasing.
 *
 * @deprecated Part of the legacy feature 'legacy_chasing'
 */
class ChaseInvoicesLegacy extends AbstractTaskQueueCronJob
{
    private const BATCH_SIZE = 250;

    public function __construct(
        private InvoiceChaser $invoiceChaser,
        private TenantContext $tenant,
        private Connection $database,
    ) {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        return $this->database->fetchFirstColumn('SELECT f.tenant_id FROM Features f JOIN Companies c ON c.id=f.tenant_id WHERE feature="legacy_chasing" AND f.enabled=1 AND c.canceled=0');
    }

    /**
     * @param int $task
     */
    public function runTask(mixed $task): bool
    {
        $company = Company::findOrFail($task);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        if (!$company->accounts_receivable_settings->allow_chasing || !$company->features->has('legacy_chasing')) {
            return false;
        }

        $invoices = $this->invoiceChaser->getInvoicesQuery($company)
            ->first(self::BATCH_SIZE);

        foreach ($invoices as $invoice) {
            $this->invoiceChaser->chaseInvoice($invoice, (string) $invoice->next_chase_step);
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return true;
    }
}
