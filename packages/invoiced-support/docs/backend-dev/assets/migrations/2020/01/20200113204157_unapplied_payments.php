<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class UnappliedPayments extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('UnappliedPayments');
        $this->addTenant($table);
        $table->addColumn('customer', 'integer', ['null' => true, 'default' => null])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('balance', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('date', 'integer')
            ->addColumn('method', 'string', ['length' => 32])
            ->addColumn('reference', 'string', ['null' => true, 'default' => null])
            ->addColumn('source', 'string', ['default' => 'keyed'])
            ->addColumn('applied', 'boolean', ['default' => false])
            ->addColumn('voided', 'boolean', ['default' => false])
            ->addTimestamps()
            ->addIndex('applied')
            ->addIndex('voided')
            ->create();
    }
}
