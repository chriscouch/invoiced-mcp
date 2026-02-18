<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DropInvoiceNumberIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Invoices')
            ->removeIndex('number')
            ->update();
    }
}
