<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerPortalAttachments extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('CustomerPortalAttachments');
        $this->addTenant($table);
        $table
            ->addColumn('file_id', 'integer')
            ->addIndex(['file_id'], ['unique' => true])
            ->addForeignKey('file_id', 'Files', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addTimestamps()
            ->create();
    }
}
