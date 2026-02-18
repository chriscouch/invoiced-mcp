<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NullOverageCharge extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('OverageCharges')
            ->changeColumn('billing_system', 'enum', ['default' => null, 'null' => true, 'values' => ['invoiced', 'stripe', 'reseller']])
            ->update();
    }
}
