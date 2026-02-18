<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Notification extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Notifications');
        $this->addTenant($table);
        $table->addColumn('event', 'string')
            ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('enabled', 'boolean', ['default' => true])
            ->addColumn('medium', 'string', ['length' => 30, 'default' => 'email'])
            ->addColumn('match_mode', 'enum', ['values' => ['any', 'all'], 'default' => 'any'])
            ->addColumn('conditions', 'text')
            ->addTimestamps()
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
