<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddShortPayThresholdToSettings extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Settings')
            ->addColumn('short_pay_units', 'enum', ['values' => ['percent', 'dollars'], 'default' => 'percent'])
            ->addColumn('short_pay_amount', 'integer', ['default' => 10])
            ->update();
    }
}
