<?php

use Phinx\Migration\AbstractMigration;

final class UserLink extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('UserLinks', ['id' => false, 'primary_key' => ['user_id', 'link']]);
        $table->addColumn('user_id', 'integer')
            ->addColumn('link', 'string', ['length' => 32])
            ->addColumn('type', 'enum', ['values' => ['reset_password', 'verify_email', 'temporary']])
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addTimestamps()
            ->create();
    }
}
