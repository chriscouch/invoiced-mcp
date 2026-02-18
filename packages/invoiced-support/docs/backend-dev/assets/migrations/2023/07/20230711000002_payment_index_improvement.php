<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentIndexImprovement extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Payments')
            ->addIndex(['tenant_id', 'reference'])
            ->addIndex(['external_id'])
            ->addIndex(['tenant_id', 'method'])
            ->addIndex(['tenant_id', 'applied'])
            ->addIndex(['tenant_id', 'voided'])
            ->addIndex(['tenant_id', 'amount'])
            ->addIndex(['tenant_id', 'updated_at'])
            ->removeIndex(['voided'])
            ->removeIndex(['applied'])
            ->update();
    }
}
