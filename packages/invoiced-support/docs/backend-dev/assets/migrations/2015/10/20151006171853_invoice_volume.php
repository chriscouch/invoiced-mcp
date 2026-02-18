<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoiceVolume extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('InvoiceVolumes');
        $this->addTenant($table);
        $table->addColumn('month', 'integer')
            ->addColumn('count', 'integer')
            ->addColumn('billed', 'boolean')
            ->create();
    }
}
