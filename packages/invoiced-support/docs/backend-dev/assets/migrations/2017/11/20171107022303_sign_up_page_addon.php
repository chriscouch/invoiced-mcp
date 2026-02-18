<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SignUpPageAddon extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('SignUpPageAddons');
        $this->addTenant($table);
        $table->addColumn('sign_up_page_id', 'integer')
            ->addColumn('plan_id', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('catalog_item_id', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('type', 'enum', ['values' => ['quantity', 'boolean']])
            ->addColumn('required', 'boolean')
            ->addColumn('recurring', 'boolean')
            ->addColumn('order', 'integer')
            ->addForeignKey('sign_up_page_id', 'SignUpPages', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->addTimestamps()
            ->create();
    }
}
