<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EmailOpen extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('EmailOpens');
        $this->addTenant($table);
        $table->addColumn('email_id', 'string')
            ->addColumn('timestamp', 'integer')
            ->addColumn('ip', 'string', ['length' => 45])
            ->addColumn('user_agent', 'string')
            ->addForeignKey('email_id', 'Emails', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('timestamp')
            ->create();
    }
}
