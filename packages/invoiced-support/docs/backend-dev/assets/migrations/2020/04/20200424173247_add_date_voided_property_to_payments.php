<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddDateVoidedPropertyToPayments extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Payments')
            ->addColumn('date_voided', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
