<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class BillingSystemProperty extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Companies')
            ->addColumn('billing_system', 'enum', ['default' => null, 'null' => true, 'values' => ['invoiced', 'stripe']])
            ->addColumn('invoiced_customer', 'string')
            ->addIndex('invoiced_customer')
            ->update();
    }
}
