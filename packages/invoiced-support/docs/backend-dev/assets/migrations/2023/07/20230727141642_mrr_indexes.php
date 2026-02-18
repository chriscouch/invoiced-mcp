<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MrrIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('MrrItems')
            ->addIndex(['version_id', 'month'])
            ->addIndex(['tenant_id', 'month'])
            ->update();

        $this->table('MrrMovements')
            ->addIndex(['version_id', 'month'])
            ->addIndex(['tenant_id', 'month'])
            ->update();
    }
}
