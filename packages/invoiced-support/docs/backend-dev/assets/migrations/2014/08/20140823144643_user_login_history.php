<?php

use Phinx\Migration\AbstractMigration;

final class UserLoginHistory extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('AccountSecurityEvents');
        $table->addColumn('user_id', 'integer')
            ->addColumn('type', 'string')
            ->addColumn('ip', 'string', ['length' => 45])
            ->addColumn('user_agent', 'string')
            ->addColumn('description', 'string')
            ->addColumn('auth_strategy', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
