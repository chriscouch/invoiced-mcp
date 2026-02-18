<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueCreditNoteNumber extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('CreditNotes')
            ->addIndex(['tenant_id', 'number'], ['unique' => true, 'name' => 'unique_number'])
            ->update();
    }
}
