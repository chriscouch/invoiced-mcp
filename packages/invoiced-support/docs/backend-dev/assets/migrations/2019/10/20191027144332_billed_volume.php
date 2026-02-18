<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class BilledVolume extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('BilledVolumes');
        $this->addTenant($table);
        $table->addColumn('month', 'integer')
            ->addColumn('count', 'integer')
            ->addColumn('billed', 'boolean')
            ->create();
    }
}
