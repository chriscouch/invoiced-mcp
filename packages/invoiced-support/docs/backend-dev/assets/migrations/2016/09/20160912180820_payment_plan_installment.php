<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentPlanInstallment extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('PaymentPlanInstallments');
        $this->addTenant($table);
        $table->addColumn('payment_plan_id', 'integer')
            ->addColumn('date', 'integer')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('balance', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addTimestamps()
            ->addForeignKey('payment_plan_id', 'PaymentPlans', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('date')
            ->addIndex('balance')
            ->create();
    }
}
