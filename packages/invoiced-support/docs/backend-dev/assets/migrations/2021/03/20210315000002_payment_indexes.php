<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentIndexes extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $this->table('Payments')
            ->addIndex(['tenant_id', 'date', 'currency'])
            ->update();
    }
}
