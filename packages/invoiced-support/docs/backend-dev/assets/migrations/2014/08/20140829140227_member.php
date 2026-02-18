<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Member extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Members');
        $this->addTenant($table);
        $table->addColumn('user_id', 'integer')
            ->addColumn('role', 'string')
            ->addColumn('expires', 'integer')
            ->addColumn('last_accessed', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('expires')
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
