<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctCustomerImportMapping extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('customer_import_mapping', 'text', ['default' => null, 'null' => true])
            ->update();
    }
}
