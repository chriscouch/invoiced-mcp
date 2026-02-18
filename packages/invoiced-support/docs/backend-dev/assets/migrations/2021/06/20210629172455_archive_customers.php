<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ArchiveCustomers extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Customers')
            ->addColumn('active', 'boolean', ['default' => 1])
            ->update();
    }
}
