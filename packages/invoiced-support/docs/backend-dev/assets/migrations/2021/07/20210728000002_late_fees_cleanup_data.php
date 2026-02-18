<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LateFeesCleanupData extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Settings')
            ->removeColumn('allow_late_fees')
            ->removeColumn('late_fee_value')
            ->removeColumn('late_fee_is_percent')
            ->removeColumn('late_fee_grace')
            ->removeColumn('late_fee_recurring_interval')
            ->removeColumn('late_fee_recurring_count')
            ->update();
    }
}
