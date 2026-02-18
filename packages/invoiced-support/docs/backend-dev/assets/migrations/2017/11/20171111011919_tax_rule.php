<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TaxRule extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('TaxRules');
        $this->addTenant($table);
        $table->addColumn('tax_rate', 'string')
            ->addColumn('state', 'string', ['null' => true, 'default' => null])
            ->addColumn('country', 'string', ['null' => true, 'default' => null, 'length' => 2])
            ->addTimestamps()
            ->create();
    }
}
