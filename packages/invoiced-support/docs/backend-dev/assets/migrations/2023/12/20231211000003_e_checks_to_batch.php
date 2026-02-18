<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class EChecksToBatch extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('EChecks')
            ->dropForeignKey('bill_id')
            ->removeColumn('bill_id')
            ->update();
    }
}
