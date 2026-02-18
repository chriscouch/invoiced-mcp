<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class RenameCatalogItem extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Metadata')
            ->changeColumn('object_type', 'enum', ['values' => ['coupon', 'tax_rate', 'catalog_item', 'customer', 'credit_note', 'estimate', 'invoice', 'item', 'line_item', 'transaction', 'plan', 'subscription']])
            ->update();
    }
}
