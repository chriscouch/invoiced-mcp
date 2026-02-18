<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class VoidedEstimates extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Estimates')
            ->addColumn('voided', 'boolean')
            ->addColumn('date_voided', 'integer', ['null' => true, 'default' => null])
            ->addIndex('voided')
            ->update();
    }
}
