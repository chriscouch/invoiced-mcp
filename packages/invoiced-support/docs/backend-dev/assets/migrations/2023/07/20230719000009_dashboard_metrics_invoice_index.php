<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DashboardMetricsInvoiceIndex extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Invoices')
            ->addIndex(['tenant_id', 'needs_attention', 'closed'])
            ->update();
    }
}
