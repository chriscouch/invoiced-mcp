<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class EventTenantIdIndex extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Events');
        if (!$table->hasIndex('tenant_id')) {
            $table->addIndex('tenant_id')->update();
        }
    }
}
