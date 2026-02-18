<?php

use App\Core\Multitenant\MultitenantModelMigration;
use Phinx\Db\Adapter\AdapterInterface;

final class EmailThreadNotes extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('EmailThreadNotes', ['id' => false, 'primary_key' => ['id']]);
        $this->addTenant($table);
        $table->addColumn('id', AdapterInterface::PHINX_TYPE_BIG_INTEGER)
            ->addColumn('thread_id', AdapterInterface::PHINX_TYPE_BIG_INTEGER)
            ->addColumn('user_id', AdapterInterface::PHINX_TYPE_INTEGER, ['null' => true, 'default' => null])
            ->addColumn('note', AdapterInterface::PHINX_TYPE_TEXT)
            ->addTimestamps()
            ->addForeignKey('thread_id', 'EmailThreads', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->save();
    }
}
