<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SubscriptionApproval extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('SubscriptionApprovals');
        $this->addTenant($table);
        $table->addColumn('subscription_id', 'integer')
            ->addColumn('timestamp', 'integer')
            ->addColumn('ip', 'string', ['length' => 45])
            ->addColumn('user_agent', 'string')
            ->addForeignKey('subscription_id', 'Subscriptions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('Subscriptions')
            ->addColumn('approval_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('approval_id', 'SubscriptionApprovals', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();
    }
}
