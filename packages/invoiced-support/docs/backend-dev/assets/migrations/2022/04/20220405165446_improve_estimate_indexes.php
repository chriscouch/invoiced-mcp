<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ImproveEstimateIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Estimates')
            ->removeIndex('date')
            ->removeIndex('draft')
            ->removeIndex('sent')
            ->removeIndex('status')
            ->removeIndex('viewed')
            ->removeIndex('voided')
            ->addIndex(['tenant_id', 'status'])
            ->update();
    }
}
