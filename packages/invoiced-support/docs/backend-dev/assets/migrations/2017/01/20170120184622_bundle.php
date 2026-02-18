<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Bundle extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Bundles', ['id' => 'internal_id']);
        $this->addTenant($table);
        $table->addColumn('id', 'string', ['collation' => 'utf8_bin'])
            ->addColumn('name', 'string')
            ->addColumn('items', 'text')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addTimestamps()
            ->addIndex('currency')
            ->addIndex('id')
            ->create();
    }
}
