<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctImportFilterSetting extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('invoice_import_query_addon', 'text', ['default' => null, 'null' => true])
            ->update();
    }
}
