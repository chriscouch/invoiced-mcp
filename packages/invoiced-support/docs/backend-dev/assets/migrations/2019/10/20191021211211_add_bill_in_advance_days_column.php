<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddBillInAdvanceDaysColumn extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Subscriptions')
            ->addColumn('bill_in_advance_days', 'integer', ['default' => 0])
            ->update();
    }
}
