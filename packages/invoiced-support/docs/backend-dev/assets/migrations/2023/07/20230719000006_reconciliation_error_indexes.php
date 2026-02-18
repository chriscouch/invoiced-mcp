<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ReconciliationErrorIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('ReconciliationErrors')
            ->addIndex(['tenant_id', 'integration_id', 'object'])
            ->removeIndex('object')
            ->removeIndex('integration_id')
            ->update();
    }
}
