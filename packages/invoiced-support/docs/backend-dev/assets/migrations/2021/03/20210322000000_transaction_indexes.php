<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TransactionIndexes extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $this->table('Transactions')
            ->dropForeignKey('tenant_id')
            ->addIndex(['tenant_id', 'payment_id', 'date'])
            ->update();
    }
}
