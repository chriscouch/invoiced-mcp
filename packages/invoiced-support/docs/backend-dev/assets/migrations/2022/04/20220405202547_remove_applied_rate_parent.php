<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveAppliedRateParent extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('AppliedRates')
            ->removeColumn('parent_type')
            ->removeColumn('parent_id')
            ->update();
    }
}
