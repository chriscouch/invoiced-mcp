<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DropCreditNoteNumberIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('CreditNotes')
            ->removeIndex('number')
            ->update();
    }
}
