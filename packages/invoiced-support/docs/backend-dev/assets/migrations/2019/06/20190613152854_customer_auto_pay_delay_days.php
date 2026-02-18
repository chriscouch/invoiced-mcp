<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerAutoPayDelayDays extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Customers')
            ->addColumn('autopay_delay_days', 'integer', ['default' => null, 'null' => true])
            ->update();
    }
}
