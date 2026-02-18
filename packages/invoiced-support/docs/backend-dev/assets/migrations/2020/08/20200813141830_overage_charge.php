<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class OverageCharge extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('OverageCharges')
            ->addColumn('tenant_id', 'integer')
            ->addColumn('month', 'integer')
            ->addColumn('dimension', 'string', ['length' => 20])
            ->addColumn('quantity', 'integer')
            ->addColumn('price', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('total', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('plan', 'string', ['length' => 50])
            ->addColumn('billed', 'boolean')
            ->addColumn('billing_system', 'string', ['length' => 20])
            ->addColumn('billing_system_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('failure_message', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex(['tenant_id', 'month', 'dimension'], ['unique' => true])
            ->create();
    }
}
