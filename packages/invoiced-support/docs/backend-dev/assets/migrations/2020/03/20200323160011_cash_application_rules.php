<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CashApplicationRules extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('CashApplicationRules');
        $this->addTenant($table);
        $table->addColumn('formula', 'string')
            ->addColumn('customer', 'integer', ['null' => true, 'default' => null])
            ->addColumn('method', 'string', ['default' => ''])
            ->addColumn('ignore', 'boolean', ['default' => false])
            ->addForeignKey('customer', 'Customers', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
