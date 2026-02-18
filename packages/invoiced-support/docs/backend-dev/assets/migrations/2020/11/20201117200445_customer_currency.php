<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerCurrency extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Customers')
            ->addColumn('currency', 'string', ['length' => 3, 'null' => true, 'default' => null])
            ->update();
    }
}
