<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class BillToParent extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Customers')
            ->addColumn('bill_to_parent', 'boolean')
            ->update();
    }
}
