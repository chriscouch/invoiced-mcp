<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Letter extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Letters', ['id' => false, 'primary_key' => ['id']]);
        $this->addTenant($table);
        $table->addColumn('id', 'string')
            ->addColumn('state', 'string')
            ->addColumn('to', 'string', ['length' => 1000])
            ->addColumn('num_pages', 'integer')
            ->addColumn('expected_delivery_date', 'integer', ['null' => true, 'default' => null])
            ->addColumn('lob_id', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->save();
    }
}
