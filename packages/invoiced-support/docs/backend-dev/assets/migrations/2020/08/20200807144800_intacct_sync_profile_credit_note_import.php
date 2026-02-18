<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctSyncProfileCreditNoteImport extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->removeColumn('credit_note_types')
            ->addColumn('read_credit_notes', 'boolean', ['default' => false])
            ->addColumn('write_credit_notes', 'boolean', ['default' => false])
            ->update();
    }
}
