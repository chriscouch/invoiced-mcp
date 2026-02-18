<?php

use Phinx\Migration\AbstractMigration;

final class user extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('Users');
        $table->addColumn('email', 'string')
            ->addColumn('password', 'string')
            ->addColumn('first_name', 'string')
            ->addColumn('last_name', 'string')
            ->addColumn('ip', 'string', ['length' => 45])
            ->addColumn('enabled', 'boolean', ['default' => true])
            ->addColumn('default_company_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('google_claimed_id', 'string', ['default' => null, 'null' => true])
            ->addColumn('intuit_claimed_id', 'string', ['default' => null, 'null' => true])
            ->addColumn('authy_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('verified_2fa', 'boolean')
            ->addColumn('has_password', 'boolean', ['default' => true])
            ->addTimestamps()
            ->addIndex('email', ['unique' => true])
            ->addIndex('google_claimed_id')
            ->addIndex('intuit_claimed_id')
            ->create();

        $this->table('GroupMembers')
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();

        $this->table('PersistentSessions')
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();
    }
}
