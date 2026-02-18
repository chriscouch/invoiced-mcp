<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctSyncProfileCustomerFilter extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('invoice_customer_filter', 'text', ['default' => null, 'null' => true])
            ->update();
    }
}
