<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveRunContext extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AutomationRuns')
            ->removeColumn('context')
            ->update();
    }
}
