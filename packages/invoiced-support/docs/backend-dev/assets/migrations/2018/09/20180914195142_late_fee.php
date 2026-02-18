<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LateFee extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('LateFees');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('invoice_id', 'integer')
            ->addColumn('line_item_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('line_item_id', 'LineItems', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
