<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ImproveEstimateIndexes2 extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Estimates')
            ->addIndex(['tenant_id', 'draft', 'voided', 'closed'])
            ->addIndex(['tenant_id', 'sent'])
            ->addIndex(['tenant_id', 'viewed'])
            ->addIndex(['tenant_id', 'deposit_paid'])
            ->addIndex(['tenant_id', 'currency'])
            ->addIndex(['tenant_id', 'date'])
            ->update();
    }
}
