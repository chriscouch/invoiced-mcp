<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Note extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Notes');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('notes', 'text')
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addTimestamps()
            ->create();
    }
}
