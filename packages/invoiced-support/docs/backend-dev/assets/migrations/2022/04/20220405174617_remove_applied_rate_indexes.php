<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveAppliedRateIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('AppliedRates')
            ->removeIndex('parent_type')
            ->removeIndex('type')
            ->update();
    }
}
