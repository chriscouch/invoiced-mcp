<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Filter extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Filters');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('type', 'integer')
            ->addColumn('private', 'boolean')
            ->addColumn('settings', 'text')
            ->addColumn('creator', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('creator', 'Members', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
