<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class File extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Files');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('size', 'integer')
            ->addColumn('type', 'string')
            ->addColumn('url', 'text')
            ->addTimestamps()
            ->create();
    }
}
