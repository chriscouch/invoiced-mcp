<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ScheduledTriggers extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AutomationWorkflowTriggers')
            ->addColumn('r_rule', 'string', ['null' => true, 'default' => null])
            ->addColumn('last_run', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('next_run', 'timestamp', ['null' => true, 'default' => null])
            ->update();
    }
}
