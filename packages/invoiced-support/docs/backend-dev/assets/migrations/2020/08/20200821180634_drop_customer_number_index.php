<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DropCustomerNumberIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Customers')
            ->removeIndex('number')
            ->update();
    }
}
