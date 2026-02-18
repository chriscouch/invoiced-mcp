<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveEventIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Events')
            ->removeIndex('object_id')
            ->removeIndex('object_type')
            ->update();
    }
}
