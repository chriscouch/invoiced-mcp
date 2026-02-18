<?php

use Phinx\Migration\AbstractMigration;

final class PersistentSession extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('PersistentSessions', ['id' => false, 'primary_key' => 'token']);
        $table->addColumn('token', 'string', ['length' => 128])
            ->addColumn('email', 'string')
            ->addColumn('series', 'string', ['length' => 128])
            ->addColumn('user_id', 'integer')
            ->addColumn('two_factor_verified', 'boolean')
            ->addTimestamps()
            ->create();
    }
}
