<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentFlowApplicationFk extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PaymentFlowApplications')
            ->dropForeignKey('payment_flow_id')
            ->update();

        $this->table('PaymentFlowApplications')
            ->addForeignKey('payment_flow_id', 'PaymentFlows', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();
    }
}
