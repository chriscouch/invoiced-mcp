<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ApprovalRemoveUnused extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('ApprovalWorkflows')->addIndex('name', ['unique' => true])->update();
        $this->table('ApprovalWorkflowSteps')
            ->removeColumn('name')
            ->removeColumn('type')
            ->update();
    }
}
