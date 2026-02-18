<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctImportPdf extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('read_pdfs', 'boolean')
            ->update();
    }
}
