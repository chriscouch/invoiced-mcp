<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class VoidedInvoices extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Invoices')
            ->addColumn('voided', 'boolean')
            ->addColumn('date_voided', 'integer', ['null' => true, 'default' => null])
            ->addIndex('voided')
            ->update();
    }
}
