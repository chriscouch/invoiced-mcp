<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AutomationRunEventType extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AutomationRuns')
            ->addColumn('event_type_id', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
