<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AvalaraLocationCode extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('CatalogItems')
            ->addColumn('avalara_location_code', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
