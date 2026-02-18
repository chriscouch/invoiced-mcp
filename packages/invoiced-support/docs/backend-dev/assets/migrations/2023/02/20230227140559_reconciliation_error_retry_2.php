<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ReconciliationErrorRetry2 extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('ReconciliationErrors')
            ->addColumn('retry', 'boolean')
            ->update();
    }
}
