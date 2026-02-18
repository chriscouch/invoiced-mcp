<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NumberLength extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Invoices')
            ->changeColumn('number', 'string', ['length' => 32])
            ->update();
        $this->table('CreditNotes')
            ->changeColumn('number', 'string', ['length' => 32])
            ->update();
    }
}
