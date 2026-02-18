<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class VoidedCreditNotes extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('CreditNotes')
            ->addColumn('voided', 'boolean')
            ->addColumn('date_voided', 'integer', ['null' => true, 'default' => null])
            ->addIndex('voided')
            ->update();
    }
}
