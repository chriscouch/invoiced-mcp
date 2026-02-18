<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentPlanApproval extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('PaymentPlanApprovals');
        $this->addTenant($table);
        $table->addColumn('payment_plan_id', 'integer')
            ->addColumn('timestamp', 'integer')
            ->addColumn('ip', 'string', ['length' => 45])
            ->addColumn('user_agent', 'string')
            ->addForeignKey('payment_plan_id', 'PaymentPlans', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('PaymentPlans')
            ->addForeignKey('approval_id', 'PaymentPlanApprovals', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();
    }
}
