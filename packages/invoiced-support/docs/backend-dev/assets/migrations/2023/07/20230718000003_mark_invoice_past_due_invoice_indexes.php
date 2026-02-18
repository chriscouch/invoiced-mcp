<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MarkInvoicePastDueInvoiceIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Invoices')
            ->addIndex(['status', 'due_date', 'tenant_id'])
            ->update();
    }
}
