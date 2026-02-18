<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class FixAutoPayDelayDays extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute('UPDATE Customers SET autopay_delay_days=-1');

        $this->table('Customers')
            ->changeColumn('autopay_delay_days', 'integer', ['default' => -1])
            ->update();
    }
}
