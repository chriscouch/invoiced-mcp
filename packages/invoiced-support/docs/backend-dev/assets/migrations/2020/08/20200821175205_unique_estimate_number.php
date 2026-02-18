<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueEstimateNumber extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Estimates')
            ->addIndex(['tenant_id', 'number'], ['unique' => true, 'name' => 'unique_number'])
            ->update();
    }
}
