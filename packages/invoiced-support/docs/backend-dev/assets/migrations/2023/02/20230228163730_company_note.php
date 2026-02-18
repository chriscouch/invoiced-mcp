<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CompanyNote extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CompanyNotes')
            ->addColumn('tenant_id', 'integer')
            ->addColumn('created_by', 'string')
            ->addColumn('note', 'text')
            ->addTimestamps()
            ->addIndex('tenant_id')
            ->create();
    }
}
