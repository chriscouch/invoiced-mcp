<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddBillInColumn extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Subscriptions')
            ->addColumn('bill_in', 'enum', ['values' => ['advance', 'arrears'], 'default' => 'advance'])
            ->update();
    }
}
