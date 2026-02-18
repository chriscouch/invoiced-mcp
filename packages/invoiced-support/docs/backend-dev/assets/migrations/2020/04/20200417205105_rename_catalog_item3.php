<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class RenameCatalogItem3 extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Metadata')
            ->changeColumn('object_type', 'enum', ['values' => ['coupon', 'tax_rate', 'customer', 'credit_note', 'estimate', 'invoice', 'item', 'line_item', 'transaction', 'plan', 'subscription']])
            ->update();
    }
}
