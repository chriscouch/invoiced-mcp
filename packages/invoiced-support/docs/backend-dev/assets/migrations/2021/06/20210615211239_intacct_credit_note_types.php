<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctCreditNoteTypes extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->changeColumn('invoice_types', 'text', ['default' => '[]'])
            ->addColumn('credit_note_types', 'text', ['default' => '[]'])
            ->update();
    }
}
