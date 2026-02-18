<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Feature extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Features');
        $this->addTenant($table);
        $table->addColumn('feature', 'string')
            ->addColumn('value', 'string')
            ->addIndex('feature')
            ->addIndex('value')
            ->create();
    }
}
