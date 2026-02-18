<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ChargePaymentFlow extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableForeignKeyChecks();
        $table = $this->table('Charges');
        if (!$table->hasColumn('payment_flow_id')) {
            $table->addColumn('payment_flow_id', 'integer', ['null' => true, 'default' => null])
                ->update();
        }
        $table->addForeignKey('payment_flow_id', 'PaymentFlows', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
        $this->enableForeignKeyChecks();
    }
}
