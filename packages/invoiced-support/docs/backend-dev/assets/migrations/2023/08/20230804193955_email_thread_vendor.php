<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class EmailThreadVendor extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableForeignKeyChecks();
        $this->table('EmailThreads')
            ->addColumn('vendor_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('vendor_id', 'Vendors', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
        $this->enableForeignKeyChecks();
    }
}
