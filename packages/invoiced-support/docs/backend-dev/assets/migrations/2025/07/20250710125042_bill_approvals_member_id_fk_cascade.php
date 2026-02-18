<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillApprovalsMemberIdFkCascade extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('BillApprovals');
        $table
            ->changeColumn('member_id', 'integer', ['null' => true, 'default' => null])
            ->dropForeignKey('member_id')
            ->addForeignKey('member_id', 'Members', 'id', ['update' => 'CASCADE', 'delete' => 'SET NULL'])
            ->update();
    }
}




