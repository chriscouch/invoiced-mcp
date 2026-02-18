<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueCustomerNumber extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Customers')
            ->addIndex(['tenant_id', 'number'], ['unique' => true, 'name' => 'unique_number'])
            ->update();
    }
}
