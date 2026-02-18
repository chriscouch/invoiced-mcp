<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentPlan extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('PaymentPlans');
        $this->addTenant($table);
        $table->addColumn('invoice_id', 'integer')
            ->addColumn('status', 'enum', ['values' => ['pending_signup', 'active', 'finished', 'canceled']])
            ->addColumn('approval_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('status')
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('Invoices')
            ->addForeignKey('payment_plan_id', 'PaymentPlans', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();
    }
}
