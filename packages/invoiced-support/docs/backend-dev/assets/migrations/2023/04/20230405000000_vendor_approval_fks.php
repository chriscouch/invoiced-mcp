<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorApprovalFks extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Vendors');
        $table->dropForeignKey('approval_workflow_id')
            ->addForeignKey('approval_workflow_id', 'ApprovalWorkflows', 'id', ['update' => 'set null', 'delete' => 'set null'])
            ->update();
    }
}
