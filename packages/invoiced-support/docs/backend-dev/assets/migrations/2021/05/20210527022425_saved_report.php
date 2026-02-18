<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SavedReport extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('SavedReports');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('definition', 'text')
            ->addColumn('private', 'boolean')
            ->addColumn('creator_id', 'integer')
            ->addTimestamps()
            ->create();
    }
}
