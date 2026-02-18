<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctSyncProfileInvoiceLocationId extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('invoice_location_id_filter', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
