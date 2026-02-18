<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AvalaraTaxCode extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('CatalogItems')
            ->addColumn('avalara_tax_code', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
