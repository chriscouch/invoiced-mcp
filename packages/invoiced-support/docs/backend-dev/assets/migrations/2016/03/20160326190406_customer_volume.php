<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerVolume extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('CustomerVolumes');
        $this->addTenant($table);
        $table->addColumn('month', 'integer')
            ->addColumn('count', 'integer')
            ->addColumn('billed', 'boolean')
            ->create();
    }
}
