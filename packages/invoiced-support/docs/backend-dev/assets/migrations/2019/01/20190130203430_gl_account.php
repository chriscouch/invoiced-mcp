<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class GlAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('GlAccounts');
        $this->addTenant($table);
        $table->addColumn('name', 'string', ['length' => 100])
            ->addColumn('code', 'string', ['length' => 15])
            ->addColumn('parent_id', 'integer', ['null' => true, 'default' => null])
            // This allows for a max of 5 levels
            ->addColumn('sort_key', 'string', ['length' => 504])
            ->addTimestamps()
            ->addIndex('code')
            ->addIndex('sort_key')
            ->create();

        $this->table('CatalogItems')
            ->addColumn('gl_account', 'string', ['null' => true, 'default' => null, 'length' => 15])
            ->update();
    }
}
