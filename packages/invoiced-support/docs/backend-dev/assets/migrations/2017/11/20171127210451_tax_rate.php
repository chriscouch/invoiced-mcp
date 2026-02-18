<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TaxRate extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('TaxRates', ['id' => 'internal_id']);
        $this->addTenant($table);
        $table->addColumn('id', 'string', ['collation' => 'utf8_bin'])
            ->addColumn('name', 'string')
            ->addColumn('is_percent', 'boolean', ['default' => true])
            ->addColumn('currency', 'string', ['length' => 3, 'null' => true, 'default' => null])
            ->addColumn('value', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('archived', 'boolean')
            ->addTimestamps()
            ->addIndex('currency')
            ->addIndex('archived')
            ->create();
    }
}
