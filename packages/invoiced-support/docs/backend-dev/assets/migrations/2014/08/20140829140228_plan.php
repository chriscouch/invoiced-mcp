<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Plan extends MultitenantModelMigration
{
    public function change()
    {
        // This is done in order to facilitate the change in migration order.
        // New migrations do not need this type of check.
        if ($this->hasTable('Plans')) {
            return;
        }

        $table = $this->table('Plans', ['id' => 'internal_id']);
        $this->addTenant($table);
        $table->addColumn('id', 'string', ['collation' => 'utf8_bin'])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('name', 'string')
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('catalog_item', 'string', ['collation' => 'utf8_bin', 'null' => true, 'default' => null])
            ->addColumn('chase', 'boolean')
            ->addColumn('interval', 'string', ['length' => 5])
            ->addColumn('interval_count', 'integer', ['length' => 5])
            ->addColumn('quantity_type', 'enum', ['values' => ['constant', 'usage'], 'default' => 'constant'])
            ->addColumn('pricing_mode', 'enum', ['values' => ['per_unit', 'tiered', 'volume'], 'default' => 'per_unit'])
            ->addColumn('tiers', 'text', ['null' => true, 'default' => null])
            ->addColumn('archived', 'boolean')
            ->addTimestamps()
            ->addIndex('id')
            ->addIndex('interval')
            ->addIndex('archived')
            ->create();
    }
}
