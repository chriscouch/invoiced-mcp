<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerOwner extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Customers')
            ->addColumn('owner_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('owner_id', 'Users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
