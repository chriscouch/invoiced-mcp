<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EstimateApproval extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('EstimateApprovals');
        $this->addTenant($table);
        $table->addColumn('estimate_id', 'integer')
            ->addColumn('timestamp', 'integer')
            ->addColumn('ip', 'string', ['length' => 45])
            ->addColumn('user_agent', 'string')
            ->addColumn('initials', 'string', ['length' => 10])
            ->addForeignKey('estimate_id', 'Estimates', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('Estimates')
            ->addForeignKey('approval_id', 'EstimateApprovals', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();
    }
}
