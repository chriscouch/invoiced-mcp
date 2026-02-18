<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AvalaraProperties extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Customers')
            ->addColumn('avalara_exemption_number', 'string', ['null' => true, 'default' => null])
            ->addColumn('avalara_entity_use_code', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
