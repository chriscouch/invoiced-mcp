<?php

use Phinx\Migration\AbstractMigration;

final class ActiveSession extends AbstractMigration
{
    public function change()
    {
        $this->table('ActiveSessions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string')
            ->addColumn('user_id', 'integer')
            ->addColumn('ip', 'string', ['length' => 45])
            ->addColumn('user_agent', 'string')
            ->addColumn('valid', 'boolean', ['default' => true])
            ->addColumn('expires', 'integer')
            ->addTimestamps()
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
