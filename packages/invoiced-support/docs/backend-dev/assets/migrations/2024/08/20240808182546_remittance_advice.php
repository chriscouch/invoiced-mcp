<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemittanceAdvice extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('RemittanceAdvice');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('payment_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('payment_date', 'date')
            ->addColumn('payment_method', 'string')
            ->addColumn('payment_reference', 'string')
            ->addColumn('total_gross_amount_paid', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('total_discount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('total_net_amount_paid', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('customer_id', 'Customers', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->addForeignKey('payment_id', 'Payments', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->create();
    }
}
