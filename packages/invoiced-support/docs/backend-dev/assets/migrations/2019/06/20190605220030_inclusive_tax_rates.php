<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InclusiveTaxRates extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('TaxRates')
            ->addColumn('inclusive', 'boolean')
            ->update();
    }
}
