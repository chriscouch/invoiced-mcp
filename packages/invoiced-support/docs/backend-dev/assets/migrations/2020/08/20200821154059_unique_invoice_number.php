<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueInvoiceNumber extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Invoices')
            ->addIndex(['tenant_id', 'number'], ['unique' => true, 'name' => 'unique_number'])
            ->update();
    }
}
