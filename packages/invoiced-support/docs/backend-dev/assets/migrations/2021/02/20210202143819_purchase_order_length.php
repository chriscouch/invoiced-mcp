<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PurchaseOrderLength extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Estimates')
            ->changeColumn('purchase_order', 'string', ['null' => true, 'default' => null, 'length' => 32])
            ->update();
        $this->table('Invoices')
            ->changeColumn('purchase_order', 'string', ['null' => true, 'default' => null, 'length' => 32])
            ->update();
        $this->table('CreditNotes')
            ->addColumn('purchase_order', 'string', ['null' => true, 'default' => null, 'length' => 32])
            ->update();
    }
}
