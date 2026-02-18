<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CatalogItems extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('CatalogItems', ['id' => 'internal_id']);
        $this->addTenant($table);
        $table->addColumn('id', 'string', ['collation' => 'utf8_bin'])
            ->addColumn('type', 'string', ['length' => 50, 'null' => true, 'default' => null])
            ->addColumn('name', 'string')
            ->addColumn('currency', 'string', ['length' => 3, 'null' => true, 'default' => null])
            ->addColumn('unit_cost', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addColumn('archived', 'boolean')
            ->addColumn('taxes', 'string')
            ->addColumn('discountable', 'boolean', ['default' => true])
            ->addColumn('taxable', 'boolean', ['default' => true])
            ->addTimestamps()
            ->addIndex('id')
            ->addIndex('currency')
            ->addIndex('archived')
            ->create();
    }
}
