<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Attachment extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Attachments', ['id' => false, 'primary_key' => ['parent_type', 'parent_id', 'file_id']]);
        $this->addTenant($table);
        $table->addColumn('parent_type', 'enum', ['values' => ['comment', 'credit_note', 'estimate', 'invoice']])
            ->addColumn('parent_id', 'integer')
            ->addColumn('file_id', 'integer')
            ->addColumn('location', 'enum', ['values' => ['attachment', 'pdf'], 'default' => 'attachment'])
            ->addTimestamps()
            ->addForeignKey('file_id', 'Files', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('location')
            ->create();
    }
}
