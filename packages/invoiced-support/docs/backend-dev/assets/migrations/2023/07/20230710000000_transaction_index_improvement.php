<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TransactionIndexImprovement extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Transactions')
            ->addIndex(['tenant_id', 'method', 'type', 'date'])
            ->addIndex(['tenant_id', 'status'])
            ->addIndex(['tenant_id', 'date'])
            ->addIndex(['tenant_id', 'type'])
            ->addIndex(['tenant_id', 'currency'])
            ->addIndex(['tenant_id', 'amount'])
            ->removeIndex(['tenant_id', 'payment_id', 'date'])
            ->removeIndex(['date'])
            ->removeIndex(['currency'])
            ->removeIndex(['updated_at'])
            ->removeIndex(['type'])
            ->removeIndex(['status'])
            ->removeIndex(['method'])
            ->removeIndex(['gateway'])
            ->update();
    }
}
