<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddPeriodStartAndEndColumns extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Subscriptions')
            ->addColumn('period_start', 'integer', ['null' => true, 'default' => null])
            ->addColumn('period_end', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
