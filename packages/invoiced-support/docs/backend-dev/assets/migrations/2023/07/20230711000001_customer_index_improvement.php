<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerIndexImprovement extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Customers')
            ->addIndex(['email'])
            ->addIndex(['tenant_id', 'updated_at'])
            ->addIndex(['tenant_id', 'type'])
            ->addIndex(['tenant_id', 'default_source_id'])
            ->addIndex(['tenant_id', 'autopay'])
            ->addIndex(['tenant_id', 'active'])
            ->addIndex(['chasing_cadence_id', 'next_chase_step_id', 'chase'])
            ->removeIndex(['autopay'])
            ->removeIndex(['updated_at'])
            ->removeIndex(['chasing_cadence_id'])
            ->update();
    }
}
