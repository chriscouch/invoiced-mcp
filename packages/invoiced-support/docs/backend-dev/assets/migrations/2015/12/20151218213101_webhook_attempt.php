<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class WebhookAttempt extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('WebhookAttempts');
        $this->addTenant($table);
        $table->addColumn('url', 'string')
            ->addColumn('event_id', 'integer')
            ->addColumn('next_attempt', 'integer', ['null' => true, 'default' => null])
            ->addColumn('payload', 'text')
            ->addColumn('attempts', 'text')
            ->addTimestamps()
            ->addIndex('event_id')
            ->addIndex('next_attempt')
            ->create();
    }
}
