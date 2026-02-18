<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class EventIndexing extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Events')
            ->addIndex(['tenant_id', 'type'])
            ->addIndex(['tenant_id', 'timestamp'])
            ->addIndex(['object_id'])
            ->removeIndex('type')
            ->removeIndex('timestamp')
            ->removeIndex('tenant_id')
            ->update();
    }
}
