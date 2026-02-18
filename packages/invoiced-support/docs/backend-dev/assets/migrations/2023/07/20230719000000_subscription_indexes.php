<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SubscriptionIndexes extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();
        $this->table('Subscriptions')
            ->addIndex(['tenant_id', 'canceled', 'finished', 'renews_next'])
            ->addIndex(['tenant_id', 'finished', 'updated_at'])
            ->addIndex(['tenant_id', 'status'])
            ->addIndex(['tenant_id', 'cycles'])
            ->removeIndex('renews_next')
            ->removeIndex('finished')
            ->removeIndex('tenant_id')
            ->removeIndex('canceled')
            ->removeIndex('status')
            ->removeIndex('paused')
            ->removeIndex('cycles')
            ->update();
    }
}
