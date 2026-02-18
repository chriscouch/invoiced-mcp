<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AutomationImprovements extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AutomationRuns')
            ->addColumn('object_type', 'smallinteger')
            ->addColumn('object_id', 'string')
            ->addColumn('event_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['tenant_id', 'object_id', 'object_type'])
            ->addForeignKey('event_id', 'Events', 'id', ['update' => 'NO_ACTION', 'delete' => 'NO_ACTION'])
            ->update();

        $this->table('AutomationWorkflowTriggers')
            ->addIndex(['tenant_id', 'event_type'])
            ->update();
    }
}
