<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountManager extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('AccountManagers');
        $this->addTenant($table);
        $table->addColumn('user_id', 'integer')
            ->addColumn('customer_id', 'integer')
            ->addTimestamps()
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
