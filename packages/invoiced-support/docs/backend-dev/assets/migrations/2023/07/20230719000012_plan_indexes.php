<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PlanIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Plans')
            ->addIndex(['tenant_id', 'interval'])
            ->addIndex(['tenant_id', 'archived'])
            ->addIndex(['tenant_id', 'interval_count'])
            ->removeIndex('tenant_id')
            ->removeIndex('archived')
            ->removeIndex('interval')
            ->update();
    }
}
