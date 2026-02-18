<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class Quota extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Quotas');
        $this->addTenant($table);
        $table->addColumn('quota_type', 'integer')
            ->addColumn('limit', 'integer')
            ->create();
    }
}
