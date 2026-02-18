<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class Dashboard extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Dashboards');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('definition', 'text')
            ->addColumn('private', 'boolean')
            ->addColumn('creator_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
