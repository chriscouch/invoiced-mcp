<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EstimateNumberLength extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Estimates')
            ->changeColumn('number', 'string', ['length' => 32])
            ->update();
    }
}
