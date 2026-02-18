<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctDocumentTypes extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('invoice_types', 'string', ['length' => 1000, 'default' => '[]'])
            ->addColumn('credit_note_types', 'string', ['length' => 1000, 'default' => '[]'])
            ->update();
    }
}
