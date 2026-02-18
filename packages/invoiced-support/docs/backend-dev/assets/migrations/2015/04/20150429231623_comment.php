<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Comment extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Comments');
        $this->addTenant($table);
        $table->addColumn('parent_type', 'enum', ['values' => ['credit_note', 'estimate', 'invoice']])
            ->addColumn('parent_id', 'integer')
            ->addColumn('text', 'text')
            ->addColumn('from_customer', 'boolean')
            ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex(['parent_type'])
            ->addIndex(['parent_id'])
            ->create();
    }
}
